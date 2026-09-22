/**
 * NexFlow CRM — Subscriptions Management JavaScript Controller
 * Real MySQL Backend Integration via admin/api/subscriptions.php.
 * Strictly 0 mock data, 100% MySQL source of truth.
 * Manages subscriptions, KPI metrics, status filters, search,
 * sorting, drawers, invoices, payments, activity logs, and notes.
 */

(function () {
    'use strict';

    const OBSOLETE_DATA_KEY = 'NexFlow_subscriptions_data';
    const COLUMNS_KEY = 'NexFlow_subscriptions_columns';

    // Purge any legacy mock data from browser localStorage
    try {
        localStorage.removeItem(OBSOLETE_DATA_KEY);
    } catch (e) {}

    // State Variables
    let subscriptions = [];
    let selectedIds = new Set();
    let currentKpiFilter = 'All';
    let currentSearch = '';
    let currentFilterStatus = 'All';
    let currentFilterPlan = 'All';
    let currentFilterCycle = 'All';
    let currentSort = 'name-asc';
    let activeSubId = null;
    let activeDrawerData = null;
    let activeDrawerTab = 'overview';
    let currentActivityFilter = 'All Activity';
    let currentNotesSearch = '';

    let availableOwners = [];
    let availableCompanies = [];
    let availablePlans = [
        'Enterprise Tier',
        'Growth Plan',
        'Professional Suite',
        'Starter Cloud'
    ];

    let currentSubPage = 1;
    const SUB_PER_PAGE = 8;
    let totalItems = 0;
    let totalPages = 1;

    // Helper: Safe HTML Escaping
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatCurrency(num) {
        const val = parseFloat(num) || 0;
        return val.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
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

    // --- API Data Fetching ---
    function fetchBootstrapData(callback) {
        fetch('api/subscriptions.php?action=bootstrap', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                const d = res.data;
                subscriptions = d.subscriptions || [];
                availableOwners = d.owners || [];
                availableCompanies = d.companies || [];
                if (d.plans && d.plans.length > 0) {
                    availablePlans = d.plans;
                }
                if (d.kpis) {
                    updateKPIs(d.kpis);
                }
                if (typeof callback === 'function') callback();
            } else {
                console.error('Failed to load subscriptions bootstrap:', res.message);
                if (typeof callback === 'function') callback();
            }
        })
        .catch(err => {
            console.error('Error fetching subscriptions bootstrap:', err);
            if (typeof callback === 'function') callback();
        });
    }

    function fetchSubscriptions(callback) {
        const params = new URLSearchParams({
            action: 'list',
            search: currentSearch,
            kpi_filter: currentKpiFilter,
            status: currentFilterStatus,
            plan: currentFilterPlan,
            billing_cycle: currentFilterCycle,
            sort: currentSort,
            page: currentSubPage,
            limit: SUB_PER_PAGE
        });

        fetch('api/subscriptions.php?' + params.toString(), {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                const d = res.data;
                subscriptions = d.subscriptions || [];
                totalItems = (d.pagination && d.pagination.total !== undefined) ? d.pagination.total : subscriptions.length;
                totalPages = (d.pagination && d.pagination.total_pages) ? d.pagination.total_pages : (Math.ceil(totalItems / SUB_PER_PAGE) || 1);
                if (d.kpis) {
                    updateKPIs(d.kpis);
                }
                renderTableFromData();
                if (typeof callback === 'function') callback(res);
            } else {
                console.error('Failed to load subscriptions:', res.message);
                renderTableFromData();
                if (typeof callback === 'function') callback(res);
            }
        })
        .catch(err => {
            console.error('Error loading subscriptions:', err);
            renderTableFromData();
            if (typeof callback === 'function') callback(null);
        });
    }

    function fetchDrawerData(subId, callback) {
        fetch(`api/subscriptions.php?action=drawer_data&id=${encodeURIComponent(subId)}`, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                activeDrawerData = res.data;
                if (typeof callback === 'function') callback(res.data);
            } else {
                console.error('Failed to load drawer data:', res.message);
                if (typeof callback === 'function') callback(null);
            }
        })
        .catch(err => {
            console.error('Error fetching drawer data:', err);
            if (typeof callback === 'function') callback(null);
        });
    }

    // Dynamic KPI Calculation & Rendering
    function updateKPIs(kpis) {
        if (!kpis) return;

        const kpiTotal = document.getElementById('kpiTotalSubs');
        const kpiActive = document.getElementById('kpiActiveSubs');
        const kpiRenew = document.getElementById('kpiRenewSoon');
        const kpiPast = document.getElementById('kpiPastDue');
        const kpiTrial = document.getElementById('kpiTrialSubs');
        const kpiPaused = document.getElementById('kpiPausedSubs');
        const kpiCancelled = document.getElementById('kpiCancelledSubs');

        const kpiMRR = document.getElementById('kpiMRR');
        const kpiARR = document.getElementById('kpiARR');
        const kpiARPU = document.getElementById('kpiARPU');

        const badgeCount = document.getElementById('subscriptionsTotalCountBadge');

        if (kpiTotal) kpiTotal.textContent = kpis.total !== undefined ? kpis.total : 0;
        if (kpiActive) kpiActive.textContent = kpis.active !== undefined ? kpis.active : 0;
        if (kpiRenew) kpiRenew.textContent = kpis.renewing_soon !== undefined ? kpis.renewing_soon : 0;
        if (kpiPast) kpiPast.textContent = kpis.past_due !== undefined ? kpis.past_due : 0;
        if (kpiTrial) kpiTrial.textContent = kpis.trial !== undefined ? kpis.trial : 0;
        if (kpiPaused) kpiPaused.textContent = kpis.paused !== undefined ? kpis.paused : 0;
        if (kpiCancelled) kpiCancelled.textContent = kpis.cancelled !== undefined ? kpis.cancelled : 0;

        if (kpiMRR) kpiMRR.textContent = '$' + formatCurrency(kpis.mrr || 0);
        if (kpiARR) kpiARR.textContent = '$' + formatCurrency(kpis.arr || 0);
        if (kpiARPU) kpiARPU.textContent = '$' + formatCurrency(kpis.arpu || 0);

        if (badgeCount) {
            const count = kpis.total !== undefined ? kpis.total : 0;
            badgeCount.textContent = `${count} ${count === 1 ? 'subscription' : 'subscriptions'}`;
        }
    }

    // Debounce timer for search
    let searchDebounceTimer = null;

    // Render Table from current state
    function renderTableFromData() {
        const tbody = document.getElementById('subscriptionsTableBody');
        if (!tbody) return;

        if (currentSubPage > totalPages) currentSubPage = totalPages;
        if (currentSubPage < 1) currentSubPage = 1;

        const startIndex = (currentSubPage - 1) * SUB_PER_PAGE;

        const pagingRange = document.getElementById("pagingRange");
        const pagingTotal = document.getElementById("pagingTotal");
        const controls = document.getElementById("paginationControls");

        if (pagingRange) pagingRange.textContent = totalItems === 0 ? "0" : `${startIndex + 1}–${Math.min(startIndex + subscriptions.length, totalItems)}`;
        if (pagingTotal) pagingTotal.textContent = totalItems;

        const container = document.querySelector('.pagination-container');
        if (container) {
            container.style.display = totalItems > SUB_PER_PAGE ? 'flex' : (totalItems === 0 ? 'none' : 'flex');
        }

        if (controls) {
            if (totalItems === 0 || totalPages <= 1) {
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
                    <button class="pagination-btn" id="prevPageBtn" ${currentSubPage === 1 ? 'disabled' : ''} onclick="window.subscriptionsApp.goToPage(${currentSubPage - 1})">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                `;

                const pageNumbers = getPaginationPages(currentSubPage, totalPages);
                pageNumbers.forEach(p => {
                    if (p === '...') {
                        html += `<span class="pagination-btn" style="border:none;background:none;cursor:default;">...</span>`;
                    } else {
                        const isActive = (p === currentSubPage);
                        html += `<button type="button" class="pagination-btn ${isActive ? 'active' : ''}" onclick="window.subscriptionsApp.goToPage(${p})">${p}</button>`;
                    }
                });

                html += `
                    <button class="pagination-btn" id="nextPageBtn" ${currentSubPage === totalPages ? 'disabled' : ''} onclick="window.subscriptionsApp.goToPage(${currentSubPage + 1})">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                `;
                controls.innerHTML = html;
            }
        }

        if (subscriptions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="12" style="text-align:center; padding:48px 20px;">
                        <div style="width:48px; height:48px; border-radius:50%; background:#F1F5F9; color:#64748B; display:flex; align-items:center; justify-content:center; margin:0 auto 12px;">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>
                        <div style="font-weight:600; font-size:14px; color:var(--text-heading); margin-bottom:4px;">No Subscriptions Found</div>
                        <p style="font-size:12.5px; color:var(--text-muted); margin:0;">${totalItems === 0 && !currentSearch && currentKpiFilter === 'All' && currentFilterStatus === 'All' ? 'Click "New Subscription" to add your first subscription.' : 'Try adjusting your search query or filters.'}</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = subscriptions.map(s => {
            const badgeClass = s.status === 'Active' ? 'badge-active' :
                               s.status === 'Trial' ? 'badge-trial' :
                               s.status === 'Past Due' ? 'badge-pastdue' :
                               s.status === 'Paused' ? 'badge-paused' : 'badge-cancelled';

            const ownerName = s.owner || 'Unassigned';
            const initials = ownerName.split(' ').map(n => n[0]).filter(Boolean).join('').substring(0, 2).toUpperCase() || '—';
            const subCode = s.subscription_code || s.id;
            const cycleUnit = s.billing_cycle === 'Monthly' ? '/mo' : (s.billing_cycle === 'Quarterly' ? '/qtr' : '/yr');

            return `
                <tr data-sub-id="${s.id}">
                    <td>
                        <div style="font-weight:600;color:var(--text-heading);font-size:13.5px;cursor:pointer;" onclick="window.subscriptionsApp.openDrawer('${s.id}')">
                            ${escapeHtml(s.name)}
                        </div>
                        <div style="font-size:11.5px;color:var(--text-secondary);margin-top:2px;">
                            ${escapeHtml(subCode)}
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--text-heading);">${escapeHtml(s.company_name || s.company || '—')}</div>
                        <div style="font-size:11px;color:var(--text-secondary);">${escapeHtml(s.project_name || s.project || 'Standard Account')}</div>
                    </td>
                    <td>
                        <span style="font-weight:500;">${escapeHtml(s.plan)}</span>
                    </td>
                    <td>${escapeHtml(s.billing_cycle || s.billingCycle || 'Monthly')}</td>
                    <td style="font-weight:700;color:var(--text-heading);">
                        $${formatCurrency(s.amount)} <span style="font-size:11px;font-weight:400;color:var(--text-secondary);">${cycleUnit}</span>
                    </td>
                    <td>
                        <span class="badge-sub ${badgeClass}">● ${escapeHtml(s.status)}</span>
                    </td>
                    <td>${escapeHtml(s.next_billing_date || s.nextBillingDate || '—')}</td>
                    <td>${escapeHtml(s.renewal_type || s.renewalType || 'Automatic')}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            <span>${escapeHtml(s.payment_method || s.paymentMethod || 'Credit Card')}</span>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <div class="avatar avatar-xs" style="background-color:#7C3AED;font-size:9px;">${escapeHtml(initials)}</div>
                            <span>${escapeHtml(ownerName)}</span>
                        </div>
                    </td>
                    <td style="text-align:right;white-space:nowrap;">
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.subscriptionsApp.openDrawer('${s.id}')">Details</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // Public Controller Window Binding
    window.subscriptionsApp = {
        get activeSubId() {
            return activeSubId;
        },

        init: function () {
            this.setupEventListeners();
            fetchBootstrapData(() => {
                fetchSubscriptions();
            });
        },

        setupEventListeners: function () {
            // Global click to close floating dropdowns and popover
            document.addEventListener('click', function (e) {
                if (!e.target.closest('#subscriptionsFilterPopover') && !e.target.closest('#btnSubFilter')) {
                    const pop = document.getElementById('subscriptionsFilterPopover');
                    if (pop) pop.classList.remove('show');
                }
                if (!e.target.closest('#subscriptionsRowDropdown') && !e.target.closest('.sub-row-menu-btn')) {
                    const menu = document.getElementById('subscriptionsRowDropdown');
                    if (menu) menu.classList.remove('show');
                }
            });

            // Escape key listener (priority: child modal -> drawer)
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('subActionModal');
                    if (modal && modal.classList.contains('show')) {
                        window.subscriptionsApp.closeModal();
                        e.stopPropagation();
                        return;
                    }
                    const pop = document.getElementById('subscriptionsFilterPopover');
                    if (pop && pop.classList.contains('show')) {
                        window.subscriptionsApp.closeFilterPopover();
                        e.stopPropagation();
                        return;
                    }
                    const drawer = document.getElementById('subscriptionsDrawer');
                    if (drawer && drawer.classList.contains('show')) {
                        window.subscriptionsApp.closeDrawer();
                        e.stopPropagation();
                        return;
                    }
                }
            });
        },

        goToPage: function (page) {
            currentSubPage = page;
            fetchSubscriptions();
        },

        // KPI Summary Card Filter
        filterByKpiCard: function (kpiType, elem) {
            currentKpiFilter = kpiType || 'All';
            currentSubPage = 1;
            document.querySelectorAll('.subscriptions-summary-card').forEach(c => {
                const attr = c.getAttribute('data-kpi');
                c.classList.toggle('active', attr === currentKpiFilter);
            });
            fetchSubscriptions();
        },

        // Filter Popover controls
        toggleFilterPopover: function (e) {
            if (e && e.stopPropagation) e.stopPropagation();
            const pop = document.getElementById('subscriptionsFilterPopover');
            if (pop) pop.classList.toggle('show');
        },

        closeFilterPopover: function () {
            const pop = document.getElementById('subscriptionsFilterPopover');
            if (pop) pop.classList.remove('show');
        },

        applyFilters: function () {
            currentFilterStatus = document.getElementById('filterSubStatus')?.value || 'All';
            currentFilterPlan = document.getElementById('filterSubPlan')?.value || 'All';
            currentFilterCycle = document.getElementById('filterSubCycle')?.value || 'All';
            currentSubPage = 1;

            const badge = document.getElementById('subFilterBadge');
            let activeFilterCount = 0;
            if (currentFilterStatus !== 'All') activeFilterCount++;
            if (currentFilterPlan !== 'All') activeFilterCount++;
            if (currentFilterCycle !== 'All') activeFilterCount++;

            if (badge) {
                if (activeFilterCount > 0) {
                    badge.textContent = activeFilterCount;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.style.display = 'none';
                }
            }

            this.closeFilterPopover();
            fetchSubscriptions();
        },

        clearFilters: function () {
            const statusSel = document.getElementById('filterSubStatus');
            const planSel = document.getElementById('filterSubPlan');
            const cycleSel = document.getElementById('filterSubCycle');
            if (statusSel) statusSel.value = 'All';
            if (planSel) planSel.value = 'All';
            if (cycleSel) cycleSel.value = 'All';

            currentFilterStatus = 'All';
            currentFilterPlan = 'All';
            currentFilterCycle = 'All';
            currentSubPage = 1;

            const badge = document.getElementById('subFilterBadge');
            if (badge) badge.style.display = 'none';

            this.closeFilterPopover();
            fetchSubscriptions();
        },

        // Search input with debounce
        handleSearch: function (query) {
            currentSearch = (query || '').trim();
            currentSubPage = 1;
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                fetchSubscriptions();
            }, 250);
        },

        // Sort input
        handleSort: function (sortVal) {
            currentSort = sortVal;
            currentSubPage = 1;
            fetchSubscriptions();
        },

        renderTable: function () {
            fetchSubscriptions();
        },

        // Row Selection Toggle
        toggleSelection: function (id, isChecked) {
            if (isChecked) selectedIds.add(id);
            else selectedIds.delete(id);
            renderTableFromData();
        },

        toggleSelectAll: function (isChecked) {
            if (isChecked) subscriptions.forEach(s => selectedIds.add(s.id));
            else selectedIds.clear();
            renderTableFromData();
        },

        // Floating Row Menu
        openRowMenu: function (e, subId) {
            e.stopPropagation();
            const sub = subscriptions.find(s => String(s.id) === String(subId));
            if (!sub) return;

            const menu = document.getElementById('subscriptionsRowDropdown');
            if (!menu) return;

            const rect = e.currentTarget.getBoundingClientRect();
            menu.style.left = `${Math.min(rect.left - 120, window.innerWidth - 190)}px`;
            menu.style.top = `${rect.bottom + 4}px`;

            const isPaused = sub.status === 'Paused';

            menu.innerHTML = `
                <button type="button" class="subscriptions-row-item" onclick="window.subscriptionsApp.openDrawer('${sub.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    View Details
                </button>
                <button type="button" class="subscriptions-row-item" onclick="window.subscriptionsApp.openEditModal('${sub.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Subscription
                </button>
                <button type="button" class="subscriptions-row-item" onclick="window.subscriptionsApp.togglePause('${sub.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="10" y1="15" x2="10" y2="9"/><line x1="14" y1="15" x2="14" y2="9"/></svg>
                    ${isPaused ? 'Resume Subscription' : 'Pause Subscription'}
                </button>
                <button type="button" class="subscriptions-row-item" onclick="window.subscriptionsApp.openChangePlanModal('${sub.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Change Plan
                </button>
                <button type="button" class="subscriptions-row-item" onclick="window.subscriptionsApp.openRecordPaymentModal('${sub.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    Record Payment
                </button>
                <button type="button" class="subscriptions-row-item" onclick="window.subscriptionsApp.openDrawer('${sub.id}');window.subscriptionsApp.switchDrawerTab('invoices');">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    View Invoices
                </button>
                <div class="subscriptions-row-divider"></div>
                <button type="button" class="subscriptions-row-item danger" onclick="window.subscriptionsApp.openCancelModal('${sub.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    Cancel Subscription
                </button>
                <button type="button" class="subscriptions-row-item danger" onclick="window.subscriptionsApp.openDeleteModal('${sub.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Delete Record
                </button>
            `;

            menu.classList.add('show');
        },

        // Details Drawer
        openDrawer: function (subId) {
            activeSubId = subId;
            const sub = subscriptions.find(s => String(s.id) === String(subId));

            const drawer = document.getElementById('subscriptionsDrawer');
            const title = document.getElementById('subDrawerTitle');
            const company = document.getElementById('subDrawerCompany');
            const badge = document.getElementById('subDrawerBadge');

            if (sub) {
                if (title) title.textContent = sub.name;
                if (company) company.textContent = `${sub.company_name || sub.company || ''} • ${sub.subscription_code || sub.id}`;
                if (badge) {
                    badge.className = `badge-sub ${sub.status === 'Active' ? 'badge-active' : sub.status === 'Trial' ? 'badge-trial' : sub.status === 'Past Due' ? 'badge-pastdue' : sub.status === 'Paused' ? 'badge-paused' : 'badge-cancelled'}`;
                    badge.textContent = `● ${sub.status}`;
                }
            }

            if (drawer) {
                drawer.style.display = 'block';
                drawer.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            // Fetch real complete drawer data from API
            fetchDrawerData(subId, (data) => {
                if (data && data.subscription) {
                    const realSub = data.subscription;
                    if (title) title.textContent = realSub.name;
                    if (company) company.textContent = `${realSub.company_name || ''} • ${realSub.subscription_code || realSub.id}`;
                    if (badge) {
                        badge.className = `badge-sub ${realSub.status === 'Active' ? 'badge-active' : realSub.status === 'Trial' ? 'badge-trial' : realSub.status === 'Past Due' ? 'badge-pastdue' : realSub.status === 'Paused' ? 'badge-paused' : 'badge-cancelled'}`;
                        badge.textContent = `● ${realSub.status}`;
                    }
                }
                this.switchDrawerTab(activeDrawerTab || 'overview');
            });
        },

        closeDrawer: function () {
            const drawer = document.getElementById('subscriptionsDrawer');
            if (drawer) {
                drawer.classList.remove('show');
                drawer.style.display = 'none';
                document.body.style.overflow = '';
            }
        },

        switchDrawerTab: function (tabName) {
            activeDrawerTab = tabName;
            document.querySelectorAll('.subscriptions-drawer-tab').forEach(t => {
                t.classList.toggle('active', t.getAttribute('data-tab') === tabName);
            });

            const sub = (activeDrawerData && activeDrawerData.subscription) ? activeDrawerData.subscription : (subscriptions.find(s => String(s.id) === String(activeSubId)) || {});
            const drawerBody = document.getElementById('subDrawerBody');
            if (!drawerBody || !sub) return;

            const panels = {
                overview: document.getElementById('subscription-tab-overview'),
                billing: document.getElementById('subscription-tab-billing'),
                invoices: document.getElementById('subscription-tab-invoices'),
                activity: document.getElementById('subscription-tab-activity'),
                notes: document.getElementById('subscription-tab-notes')
            };

            // Hide ALL panels first
            Object.keys(panels).forEach(key => {
                const panel = panels[key];
                if (panel) {
                    panel.style.display = 'none';
                    panel.classList.remove('active');
                }
            });

            // Target panel
            const activePanel = panels[tabName] || panels.overview;
            activePanel.style.display = 'block';
            activePanel.classList.add('active');
            drawerBody.scrollTop = 0;

            const badgeClass = sub.status === 'Active' ? 'badge-active' :
                               sub.status === 'Trial' ? 'badge-trial' :
                               sub.status === 'Past Due' ? 'badge-pastdue' :
                               sub.status === 'Paused' ? 'badge-paused' : 'badge-cancelled';

            const amountVal = parseFloat(sub.amount) || 0;
            const cycleStr = sub.billing_cycle || sub.billingCycle || 'Monthly';
            const companyName = sub.company_name || sub.company || '—';
            const subCode = sub.subscription_code || sub.id || '—';
            const projectName = sub.project_name || sub.project || 'Standard Account';
            const ownerName = sub.owner || 'Unassigned';

            if (tabName === 'overview') {
                activePanel.innerHTML = `
                    <!-- SECTION 1: SUBSCRIPTION SUMMARY (2 Compact Cards) -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div style="background:#F8FAFC;border:1px solid var(--border-card);border-radius:8px;padding:14px 16px;">
                            <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Plan Tier</div>
                            <div style="font-size:16px;font-weight:700;color:var(--text-heading);margin-top:4px;">${escapeHtml(sub.plan || '—')}</div>
                        </div>
                        <div style="background:#F8FAFC;border:1px solid var(--border-card);border-radius:8px;padding:14px 16px;">
                            <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Billing Recurrence</div>
                            <div style="font-size:16px;font-weight:700;color:var(--text-heading);margin-top:4px;">$${formatCurrency(amountVal)} / ${escapeHtml(cycleStr)}</div>
                        </div>
                    </div>

                    <!-- SECTION 2: SUBSCRIPTION DETAILS -->
                    <div style="margin-bottom:20px;border-bottom:1px solid var(--border-divider);padding-bottom:18px;">
                        <div style="font-size:13.5px;font-weight:600;color:var(--text-heading);margin-bottom:12px;">Subscription Details</div>
                        <div style="display:flex;flex-direction:column;gap:10px;font-size:13px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="color:var(--text-secondary);">Start Date</span>
                                <strong style="color:var(--text-heading);font-weight:600;">${escapeHtml(sub.start_date || sub.startDate || '—')}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="color:var(--text-secondary);">Next Billing Date</span>
                                <strong style="color:var(--text-heading);font-weight:600;">${escapeHtml(sub.next_billing_date || sub.nextBillingDate || '—')}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="color:var(--text-secondary);">Renewal Type</span>
                                <strong style="color:var(--text-heading);font-weight:600;">${escapeHtml(sub.renewal_type || sub.renewalType || 'Automatic')}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="color:var(--text-secondary);">Account Manager / Owner</span>
                                <strong style="color:var(--text-heading);font-weight:600;">${escapeHtml(ownerName)}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: COMMERCIAL & ACCOUNT DETAILS -->
                    <div style="margin-bottom:20px;border-bottom:1px solid var(--border-divider);padding-bottom:18px;">
                        <div style="font-size:13.5px;font-weight:600;color:var(--text-heading);margin-bottom:12px;">Account &amp; Commercial Details</div>
                        <div style="display:flex;flex-direction:column;gap:10px;font-size:13px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="color:var(--text-secondary);">Customer / Company</span>
                                <strong style="color:var(--text-heading);font-weight:600;">${escapeHtml(companyName)}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="color:var(--text-secondary);">Subscription ID</span>
                                <strong style="color:var(--text-heading);font-weight:600;">${escapeHtml(subCode)}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="color:var(--text-secondary);">Subscription Status</span>
                                <span class="badge-sub ${badgeClass}" style="cursor:pointer;" title="Click to update status" onclick="window.subscriptionsApp.openStatusChangeModal('${sub.id}')">● ${escapeHtml(sub.status || 'Active')}</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="color:var(--text-secondary);">Project / Package</span>
                                <strong style="color:var(--text-heading);font-weight:600;">${escapeHtml(projectName)}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4: INTERNAL CONTRACT SCOPE -->
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <div style="font-size:13.5px;font-weight:600;color:var(--text-heading);">Internal Contract Scope</div>
                            <button type="button" class="btn btn-ghost btn-xs" style="color:var(--primary);" onclick="window.subscriptionsApp.openEditScopeModal('${sub.id}')">Edit Scope</button>
                        </div>
                        <div style="background:#F8FAFC;border:1px solid var(--border-divider);border-radius:8px;padding:14px 16px;font-size:13px;color:var(--text-body);line-height:1.6;white-space:pre-wrap;">${escapeHtml(sub.notes || 'No contract scope details added.')}</div>
                    </div>
                `;
            } else if (tabName === 'billing') {
                const mrr = cycleStr === 'Monthly' ? amountVal : (cycleStr === 'Quarterly' ? Math.round(amountVal / 3) : Math.round(amountVal / 12));
                const arr = mrr * 12;
                const payMethod = sub.payment_method || sub.paymentMethod || 'Credit Card';
                const billingAddress = sub.billing_address || sub.billingAddress || '';

                activePanel.innerHTML = `
                    <!-- SECTION 1: REVENUE SUMMARY -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div style="background:#F8FAFC;border:1px solid var(--border-card);border-radius:8px;padding:14px 16px;">
                            <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Calculated MRR</div>
                            <div style="font-size:17px;font-weight:700;color:var(--text-heading);margin-top:4px;">$${formatCurrency(mrr)} / mo</div>
                        </div>
                        <div style="background:#F8FAFC;border:1px solid var(--border-card);border-radius:8px;padding:14px 16px;">
                            <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Calculated ARR</div>
                            <div style="font-size:17px;font-weight:700;color:var(--text-heading);margin-top:4px;">$${formatCurrency(arr)} / yr</div>
                        </div>
                    </div>

                    <!-- SECTION 2: PAYMENT METHOD -->
                    <div style="background:#F8FAFC;border:1px solid var(--border-card);border-radius:8px;padding:16px;margin-bottom:20px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <div style="font-size:11.5px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;">Payment Method</div>
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.subscriptionsApp.openChangePaymentModal('${sub.id}')">Change Payment Method</button>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:40px;height:26px;background:#FFFFFF;border:1px solid var(--border-divider);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:10.5px;font-weight:700;color:var(--text-heading);">PAY</div>
                                <div>
                                    <div style="font-size:13.5px;font-weight:600;color:var(--text-heading);">${escapeHtml(payMethod)}</div>
                                    <div style="font-size:11.5px;color:var(--text-muted);margin-top:1px;">Primary recurring payment profile</div>
                                </div>
                            </div>
                            <span class="badge-sub badge-active">Default</span>
                        </div>
                    </div>

                    <!-- SECTION 3: BILLING ADDRESS -->
                    <div style="background:#F8FAFC;border:1px solid var(--border-card);border-radius:8px;padding:16px;margin-bottom:20px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <div style="font-size:11.5px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;">Billing Address</div>
                            <button type="button" class="btn btn-ghost btn-xs" style="color:var(--primary);" onclick="window.subscriptionsApp.openEditBillingAddressModal('${sub.id}')">Edit Billing Address</button>
                        </div>
                        <div style="font-size:13px;line-height:1.6;color:var(--text-body);">
                            <strong style="color:var(--text-heading);">${escapeHtml(companyName)}</strong><br>
                            ${billingAddress ? escapeHtml(billingAddress).replace(/\n/g, '<br>') : '<span style="color:var(--text-muted);">No billing address provided.</span>'}
                        </div>
                    </div>

                    <!-- SECTION 4: BILLING SCHEDULE OVERVIEW -->
                    <div style="font-size:13px;border-top:1px solid var(--border-divider);padding-top:16px;">
                        <div style="font-size:13.5px;font-weight:600;color:var(--text-heading);margin-bottom:12px;">Billing Schedule Overview</div>
                        <div style="display:flex;flex-direction:column;gap:10px;font-size:13px;">
                            <div style="display:flex;justify-content:space-between;">
                                <span style="color:var(--text-secondary);">Billing Cycle:</span>
                                <strong style="color:var(--text-heading);font-weight:600;">${escapeHtml(cycleStr)}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;">
                                <span style="color:var(--text-secondary);">Current Amount:</span>
                                <strong style="color:var(--text-heading);font-weight:600;">$${formatCurrency(amountVal)}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;">
                                <span style="color:var(--text-secondary);">Next Billing Date:</span>
                                <strong style="color:var(--text-heading);font-weight:600;">${escapeHtml(sub.next_billing_date || sub.nextBillingDate || '—')}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;">
                                <span style="color:var(--text-secondary);">Renewal Type:</span>
                                <strong style="color:var(--text-heading);font-weight:600;">${escapeHtml(sub.renewal_type || sub.renewalType || 'Automatic')}</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;">
                                <span style="color:var(--text-secondary);">Payment Status:</span>
                                <span class="badge-sub ${badgeClass}">● ${escapeHtml(sub.status || 'Active')}</span>
                            </div>
                        </div>
                    </div>
                `;
            } else if (tabName === 'invoices') {
                const invoices = (activeDrawerData && activeDrawerData.invoices) ? activeDrawerData.invoices : [];
                activePanel.innerHTML = `
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <h4 style="font-size:14px;font-weight:600;color:var(--text-heading);margin:0;">Invoice History</h4>
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.subscriptionsApp.openRecordPaymentModal('${sub.id}')">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                             Record Payment
                        </button>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        ${invoices.length > 0 ? invoices.map(inv => `
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:#F8FAFC;border:1px solid var(--border-card);border-radius:8px;">
                                <div>
                                    <div style="font-size:13.5px;font-weight:600;color:var(--text-heading);">${escapeHtml(inv.invoice_number || inv.id)}</div>
                                    <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">Issued ${escapeHtml(inv.invoice_date || inv.date || '—')} • <strong style="color:var(--text-heading);">$${formatCurrency(inv.amount)}</strong></div>
                                </div>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span class="badge-sub ${inv.status === 'Paid' ? 'badge-active' : (inv.status === 'Trial' ? 'badge-trial' : 'badge-pastdue')}">${escapeHtml(inv.status)}</span>
                                    <button type="button" class="btn btn-secondary btn-xs" onclick="window.subscriptionsApp.previewInvoice('${inv.id}', '${sub.id}')">View</button>
                                </div>
                            </div>
                        `).join('') : `
                            <div style="text-align:center;padding:36px 16px;background:#F8FAFC;border:1px dashed var(--border-divider);border-radius:8px;">
                                <div style="font-size:13px;font-weight:600;color:var(--text-heading);">No Invoices Recorded</div>
                                <p style="font-size:12px;color:var(--text-muted);margin:4px 0 0 0;">Click "Record Payment" to issue an invoice and payment record.</p>
                            </div>
                        `}
                    </div>
                `;
            } else if (tabName === 'activity') {
                const activityList = (activeDrawerData && activeDrawerData.activities) ? activeDrawerData.activities : [];
                const currentFilter = currentActivityFilter || 'All Activity';
                const filtered = activityList.filter(a => {
                    if (currentFilter === 'All Activity' || currentFilter === 'All') return true;
                    const type = (a.type || a.action || '').toLowerCase();
                    const text = (a.description || a.title || '').toLowerCase();
                    const title = (a.title || '').toLowerCase();
                    const actor = (a.actor || a.user_name || '').toLowerCase();

                    if (currentFilter === 'Billing') {
                        return type.includes('billing') || text.includes('billing') || text.includes('invoice') || text.includes('payment method') || title.includes('invoice');
                    }
                    if (currentFilter === 'Subscription') {
                        return type.includes('subscription') || title.includes('subscription') || text.includes('subscription');
                    }
                    if (currentFilter === 'Payments') {
                        return type.includes('payment') || title.includes('payment') || text.includes('payment') || text.includes('paid');
                    }
                    if (currentFilter === 'Plan Changes') {
                        return type.includes('plan') || title.includes('plan') || text.includes('plan') || text.includes('tier');
                    }
                    if (currentFilter === 'Account Changes') {
                        return type.includes('account') || title.includes('account') || text.includes('scope') || text.includes('address');
                    }
                    if (currentFilter === 'System') {
                        return type.includes('system') || actor.includes('system');
                    }
                    return true;
                });

                activePanel.innerHTML = `
                    <!-- ACTIVITY HEADER -->
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--border-divider);">
                        <h4 style="font-size:14.5px;font-weight:600;color:var(--text-heading);margin:0;">Activity</h4>
                        <div style="position:relative;">
                            <select class="input-control input-sm" style="font-size:12.5px;height:32px;padding:0 28px 0 10px;cursor:pointer;font-weight:500;color:var(--text-heading);background:#FFFFFF;border:1px solid var(--border-divider);border-radius:6px;" onchange="window.subscriptionsApp.setActivityFilter(this.value)">
                                <option value="All Activity" ${currentFilter === 'All Activity' ? 'selected' : ''}>All Activity</option>
                                <option value="Billing" ${currentFilter === 'Billing' ? 'selected' : ''}>Billing</option>
                                <option value="Subscription" ${currentFilter === 'Subscription' ? 'selected' : ''}>Subscription</option>
                                <option value="Payments" ${currentFilter === 'Payments' ? 'selected' : ''}>Payments</option>
                                <option value="Plan Changes" ${currentFilter === 'Plan Changes' ? 'selected' : ''}>Plan Changes</option>
                                <option value="Account Changes" ${currentFilter === 'Account Changes' ? 'selected' : ''}>Account Changes</option>
                                <option value="System" ${currentFilter === 'System' ? 'selected' : ''}>System</option>
                            </select>
                        </div>
                    </div>

                    <!-- ACTIVITY TIMELINE -->
                    ${filtered.length > 0 ? `
                        <div style="position:relative;padding-left:18px;margin-top:8px;">
                            <div style="position:absolute;left:7px;top:8px;bottom:16px;width:2px;background:var(--border-divider);"></div>
                            <div style="display:flex;flex-direction:column;gap:22px;">
                                ${filtered.map(a => {
                                    const title = a.title || 'Subscription Event';
                                    const actor = a.actor || a.user_name || 'System';
                                    const date = a.date || a.formatted_date || a.created_at || 'Just now';
                                    const desc = a.description || '';

                                    let dotBg = '#EFF6FF';
                                    let dotColor = '#0284C7';
                                    const tLower = title.toLowerCase();
                                    if (tLower.includes('payment') || tLower.includes('activated') || (a.status && a.status === 'Paid')) {
                                        dotBg = '#DCFCE7';
                                        dotColor = '#16A34A';
                                    } else if (actor.toLowerCase().includes('system') || tLower.includes('invoice')) {
                                        dotBg = '#F1F5F9';
                                        dotColor = '#64748B';
                                    } else if (tLower.includes('failed') || tLower.includes('cancelled') || tLower.includes('past due')) {
                                        dotBg = '#FEE2E2';
                                        dotColor = '#DC2626';
                                    }

                                    let badgeHtml = '';
                                    if (a.badge) {
                                        badgeHtml = `<div style="display:inline-block;margin-top:8px;padding:3px 8px;background:#F8FAFC;border:1px solid var(--border-divider);border-radius:4px;font-size:12px;font-weight:600;color:var(--text-heading);">${escapeHtml(a.badge)}</div>`;
                                    } else if (a.status) {
                                        badgeHtml = `<div style="display:inline-block;margin-top:8px;padding:2px 8px;border-radius:4px;font-size:11.5px;font-weight:600;background:${a.status === 'Paid' ? '#DCFCE7' : '#FEE2E2'};color:${a.status === 'Paid' ? '#15803D' : '#B91C1C'};">Status: ${escapeHtml(a.status)}</div>`;
                                    }

                                    return `
                                        <div style="position:relative;padding-left:22px;">
                                            <div style="position:absolute;left:-18px;top:2px;width:16px;height:16px;border-radius:50%;background:${dotBg};border:2px solid ${dotColor};display:flex;align-items:center;justify-content:center;z-index:2;">
                                                <div style="width:6px;height:6px;border-radius:50%;background:${dotColor};"></div>
                                            </div>
                                            <div>
                                                <div style="display:flex;align-items:baseline;justify-content:space-between;gap:8px;">
                                                    <span style="font-size:14px;font-weight:600;color:var(--text-heading);">${escapeHtml(title)}</span>
                                                    <span style="font-size:12px;color:var(--text-muted);white-space:nowrap;">${escapeHtml(date)}</span>
                                                </div>
                                                <div style="font-size:13px;font-weight:500;color:var(--text-secondary);margin-top:2px;">${escapeHtml(actor)}</div>
                                                <div style="font-size:14px;color:var(--text-body);margin-top:6px;line-height:1.5;white-space:pre-wrap;">${escapeHtml(desc)}</div>
                                                ${badgeHtml}
                                            </div>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    ` : `
                        <div style="text-align:center;padding:40px 16px;background:#F8FAFC;border:1px dashed var(--border-divider);border-radius:8px;margin-top:10px;">
                            <div style="width:36px;height:36px;border-radius:50%;background:#F1F5F9;color:var(--text-muted);display:flex;align-items:center;justify-content:center;margin:0 auto 10px auto;">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            </div>
                            <div style="font-size:14px;font-weight:600;color:var(--text-heading);">No activity found</div>
                            <p style="font-size:13px;color:var(--text-muted);margin:4px 0 0 0;">There are no subscription activities matching this filter.</p>
                        </div>
                    `}
                `;
            } else if (tabName === 'notes') {
                const notesList = (activeDrawerData && activeDrawerData.notes) ? activeDrawerData.notes : [];
                const searchQ = (currentNotesSearch || '').toLowerCase().trim();
                const filteredNotes = notesList.filter(n => {
                    if (!searchQ) return true;
                    const txt = (n.content || n.text || '').toLowerCase();
                    const aut = (n.author || n.user_name || '').toLowerCase();
                    const dt = (n.date || n.formatted_date || '').toLowerCase();
                    return txt.includes(searchQ) || aut.includes(searchQ) || dt.includes(searchQ);
                });

                const pinnedNotes = filteredNotes.filter(n => Boolean(n.is_pinned || n.pinned));
                const unpinnedNotes = filteredNotes.filter(n => !Boolean(n.is_pinned || n.pinned));

                activePanel.innerHTML = `
                    <!-- TOP BAR: Search & + Add Note -->
                    <div style="display:flex;gap:12px;margin-bottom:20px;align-items:center;">
                        <div style="position:relative;flex:1;">
                            <svg width="14" height="14" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" class="input-control" id="subNotesSearchInput" placeholder="Search notes..." style="padding-left:34px;height:36px;font-size:13px;" value="${escapeHtml(currentNotesSearch || '')}" oninput="window.subscriptionsApp.handleNotesSearch(this.value)">
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" style="height:36px;padding:0 14px;font-size:13px;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;" onclick="window.subscriptionsApp.openAddNoteModal('${sub.id}')">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            + Add Note
                        </button>
                    </div>

                    ${filteredNotes.length === 0 ? `
                        <div style="text-align:center;padding:40px 16px;background:#F8FAFC;border:1px dashed var(--border-divider);border-radius:8px;margin-top:10px;">
                            <div style="width:36px;height:36px;border-radius:50%;background:#F1F5F9;color:var(--text-muted);display:flex;align-items:center;justify-content:center;margin:0 auto 10px auto;">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            </div>
                            <div style="font-size:14px;font-weight:600;color:var(--text-heading);">No notes found</div>
                            <p style="font-size:13px;color:var(--text-muted);margin:4px 0 0 0;">${searchQ ? 'Try adjusting your search.' : 'Add your first note to keep track of important subscription information.'}</p>
                        </div>
                    ` : `
                        ${pinnedNotes.length > 0 ? `
                            <div style="margin-bottom:20px;">
                                <div style="font-size:11.5px;font-weight:700;color:#0284C7;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
                                    <span>PINNED NOTES</span>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:12px;">
                                    ${pinnedNotes.map(n => `
                                        <div style="background:#F0F9FF;border:1px solid #BAE6FD;border-left:3px solid #0284C7;border-radius:8px;padding:16px;">
                                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                                <span style="font-size:14px;font-weight:600;color:var(--text-heading);">${escapeHtml(n.author || n.user_name || 'Team Member')}</span>
                                                <span style="font-size:12.5px;color:var(--text-muted);">${escapeHtml(n.date || n.formatted_date || '')}</span>
                                            </div>
                                            <div style="font-size:14px;color:var(--text-body);line-height:1.5;margin-top:8px;white-space:pre-wrap;">${escapeHtml(n.content || n.text || '')}</div>
                                            <div style="display:flex;justify-content:flex-end;gap:14px;margin-top:12px;border-top:1px solid #E0F2FE;padding-top:10px;">
                                                <button type="button" class="btn-note-action pinned" style="background:none;border:none;color:#0284C7;font-size:13px;font-weight:600;cursor:pointer;padding:0;" onclick="window.subscriptionsApp.togglePinNote('${sub.id}', '${n.id}')">📌 Pinned</button>
                                                <button type="button" class="btn-note-action" style="background:none;border:none;color:var(--text-secondary);font-size:13px;font-weight:500;cursor:pointer;padding:0;" onclick="window.subscriptionsApp.openEditNoteModal('${sub.id}', '${n.id}')">Edit</button>
                                                <button type="button" class="btn-note-action danger" style="background:none;border:none;color:#DC2626;font-size:13px;font-weight:500;cursor:pointer;padding:0;" onclick="window.subscriptionsApp.confirmDeleteNote('${sub.id}', '${n.id}')">Delete</button>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}

                        ${unpinnedNotes.length > 0 ? `
                            <div>
                                <div style="font-size:11.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px;">RECENT NOTES</div>
                                <div style="display:flex;flex-direction:column;gap:12px;">
                                    ${unpinnedNotes.map(n => `
                                        <div style="background:#FFFFFF;border:1px solid var(--border-divider);border-radius:8px;padding:16px;">
                                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                                <span style="font-size:14px;font-weight:600;color:var(--text-heading);">${escapeHtml(n.author || n.user_name || 'Team Member')}</span>
                                                <span style="font-size:12.5px;color:var(--text-muted);">${escapeHtml(n.date || n.formatted_date || '')}</span>
                                            </div>
                                            <div style="font-size:14px;color:var(--text-body);line-height:1.5;margin-top:8px;white-space:pre-wrap;">${escapeHtml(n.content || n.text || '')}</div>
                                            <div style="display:flex;justify-content:flex-end;gap:14px;margin-top:12px;border-top:1px solid var(--border-divider);padding-top:10px;">
                                                <button type="button" class="btn-note-action" style="background:none;border:none;color:var(--primary);font-size:13px;font-weight:500;cursor:pointer;padding:0;" onclick="window.subscriptionsApp.togglePinNote('${sub.id}', '${n.id}')">📌 Pin</button>
                                                <button type="button" class="btn-note-action" style="background:none;border:none;color:var(--text-secondary);font-size:13px;font-weight:500;cursor:pointer;padding:0;" onclick="window.subscriptionsApp.openEditNoteModal('${sub.id}', '${n.id}')">Edit</button>
                                                <button type="button" class="btn-note-action danger" style="background:none;border:none;color:#DC2626;font-size:13px;font-weight:500;cursor:pointer;padding:0;" onclick="window.subscriptionsApp.confirmDeleteNote('${sub.id}', '${n.id}')">Delete</button>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                    `}
                `;
            }
        },

        // Status change modal
        openStatusChangeModal: function (subId) {
            const sub = (activeDrawerData && activeDrawerData.subscription) ? activeDrawerData.subscription : (subscriptions.find(s => String(s.id) === String(subId)) || {});
            if (!sub || !sub.id) return;

            const html = `
                <div class="subscriptions-modal-header">
                    <h3 class="subscriptions-modal-title">Change Subscription Status</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.subscriptionsApp.submitStatusChange(event, '${sub.id}')">
                    <div class="subscriptions-modal-body">
                        <p style="font-size:13px;color:var(--text-secondary);margin-top:0;">Update current status for <strong>${escapeHtml(sub.name)}</strong> (${escapeHtml(sub.company_name || sub.company || '')}).</p>
                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label">Subscription Status *</label>
                            <select class="input-control" id="statusChangeVal">
                                <option value="Active" ${sub.status === 'Active' ? 'selected' : ''}>Active</option>
                                <option value="Trial" ${sub.status === 'Trial' ? 'selected' : ''}>Trial</option>
                                <option value="Past Due" ${sub.status === 'Past Due' ? 'selected' : ''}>Past Due</option>
                                <option value="Paused" ${sub.status === 'Paused' ? 'selected' : ''}>Paused</option>
                                <option value="Cancelled" ${sub.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Reason / Internal Note</label>
                            <textarea class="input-control" id="statusChangeNote" rows="2" style="height:60px;resize:none;" placeholder="Optional reason for status change..."></textarea>
                        </div>
                    </div>
                    <div class="subscriptions-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitStatusChange: function (e, subId) {
            e.preventDefault();
            const newStatus = document.getElementById('statusChangeVal').value;
            const note = document.getElementById('statusChangeNote').value.trim();

            const fd = new FormData();
            fd.append('action', 'change_status');
            fd.append('id', subId);
            fd.append('status', newStatus);
            fd.append('note', note);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.closeModal();
                    this.showToast(res.message || `Subscription status updated to ${newStatus}.`, 'success');
                    fetchSubscriptions();
                    if (activeSubId === subId) {
                        this.openDrawer(subId);
                    }
                } else {
                    this.showToast(res.message || 'Failed to update status.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while updating status.', 'error');
            });
        },

        openEditScopeModal: function (subId) {
            const sub = (activeDrawerData && activeDrawerData.subscription) ? activeDrawerData.subscription : (subscriptions.find(s => String(s.id) === String(subId)) || {});
            if (!sub || !sub.id) return;

            const html = `
                <div class="subscriptions-modal-header">
                    <h3 class="subscriptions-modal-title">Edit Internal Contract Scope</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.subscriptionsApp.submitEditScope(event, '${sub.id}')">
                    <div class="subscriptions-modal-body">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Internal Contract Scope Details</label>
                            <textarea class="input-control" id="scopeTextVal" rows="4" style="height:110px;resize:none;" placeholder="Enter contract scope details...">${escapeHtml(sub.notes || '')}</textarea>
                        </div>
                    </div>
                    <div class="subscriptions-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Scope</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitEditScope: function (e, subId) {
            e.preventDefault();
            const notes = document.getElementById('scopeTextVal').value.trim();

            const fd = new FormData();
            fd.append('action', 'edit_scope');
            fd.append('id', subId);
            fd.append('notes', notes);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.closeModal();
                    this.showToast(res.message || 'Contract scope updated.', 'success');
                    if (activeSubId === subId) {
                        this.openDrawer(subId);
                    }
                } else {
                    this.showToast(res.message || 'Failed to update scope.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while updating scope.', 'error');
            });
        },

        openChangePaymentModal: function (subId) {
            const sub = (activeDrawerData && activeDrawerData.subscription) ? activeDrawerData.subscription : (subscriptions.find(s => String(s.id) === String(subId)) || {});
            if (!sub || !sub.id) return;

            const currentMethod = sub.payment_method || sub.paymentMethod || 'Credit Card';

            const html = `
                <div class="subscriptions-modal-header">
                    <h3 class="subscriptions-modal-title">Change Payment Method</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.subscriptionsApp.submitChangePayment(event, '${sub.id}')">
                    <div class="subscriptions-modal-body">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Payment Method Profile *</label>
                            <input type="text" class="input-control" id="payMethodVal" value="${escapeHtml(currentMethod)}" placeholder="e.g. Credit Card, ACH Bank Wire, PayPal" required>
                        </div>
                    </div>
                    <div class="subscriptions-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Update Method</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitChangePayment: function (e, subId) {
            e.preventDefault();
            const payMethod = document.getElementById('payMethodVal').value.trim();
            if (!payMethod) return;

            const fd = new FormData();
            fd.append('action', 'change_payment_method');
            fd.append('id', subId);
            fd.append('payment_method', payMethod);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.closeModal();
                    this.showToast(res.message || 'Payment method updated successfully.', 'success');
                    fetchSubscriptions();
                    if (activeSubId === subId) {
                        this.openDrawer(subId);
                    }
                } else {
                    this.showToast(res.message || 'Failed to update payment method.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while updating payment method.', 'error');
            });
        },

        openEditBillingAddressModal: function (subId) {
            const sub = (activeDrawerData && activeDrawerData.subscription) ? activeDrawerData.subscription : (subscriptions.find(s => String(s.id) === String(subId)) || {});
            if (!sub || !sub.id) return;

            const currentAddress = sub.billing_address || sub.billingAddress || '';

            const html = `
                <div class="subscriptions-modal-header">
                    <h3 class="subscriptions-modal-title">Edit Billing Address</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.subscriptionsApp.submitEditBillingAddress(event, '${sub.id}')">
                    <div class="subscriptions-modal-body">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Billing Address</label>
                            <textarea class="input-control" id="billingAddrVal" rows="3" style="height:80px;resize:none;" placeholder="Enter billing address...">${escapeHtml(currentAddress)}</textarea>
                        </div>
                    </div>
                    <div class="subscriptions-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Address</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitEditBillingAddress: function (e, subId) {
            e.preventDefault();
            const addr = document.getElementById('billingAddrVal').value.trim();

            const fd = new FormData();
            fd.append('action', 'edit_billing_address');
            fd.append('id', subId);
            fd.append('billing_address', addr);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.closeModal();
                    this.showToast(res.message || 'Billing address updated.', 'success');
                    if (activeSubId === subId) {
                        this.openDrawer(subId);
                    }
                } else {
                    this.showToast(res.message || 'Failed to update billing address.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while updating address.', 'error');
            });
        },

        // Activity timeline filter
        setActivityFilter: function (filterName) {
            currentActivityFilter = filterName;
            this.switchDrawerTab('activity');
        },

        // Notes Tab Handlers
        handleNotesSearch: function (query) {
            currentNotesSearch = (query || '').trim();
            this.switchDrawerTab('notes');
        },

        openAddNoteModal: function (subId) {
            const html = `
                <div class="subscriptions-modal-header">
                    <h3 class="subscriptions-modal-title">Add Note</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.subscriptionsApp.submitAddNote(event, '${subId}')">
                    <div class="subscriptions-modal-body">
                        <div class="form-group" style="margin-bottom:14px;">
                            <label class="form-label">Note *</label>
                            <textarea class="input-control" id="addNoteTextVal" rows="4" style="height:110px;resize:none;" placeholder="Write a note about this subscription..." required></textarea>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" class="input-checkbox" id="addNotePinVal">
                            <label for="addNotePinVal" style="font-size:13px;color:var(--text-heading);cursor:pointer;user-select:none;">Pin this note</label>
                        </div>
                    </div>
                    <div class="subscriptions-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Note</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitAddNote: function (e, subId) {
            e.preventDefault();
            const text = document.getElementById('addNoteTextVal').value.trim();
            const isPinned = document.getElementById('addNotePinVal').checked ? 1 : 0;
            if (!text) return;

            const fd = new FormData();
            fd.append('action', 'add_note');
            fd.append('subscription_id', subId);
            fd.append('content', text);
            fd.append('is_pinned', isPinned);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.closeModal();
                    this.showToast(res.message || 'Note added successfully.', 'success');
                    fetchDrawerData(subId, () => {
                        this.switchDrawerTab('notes');
                    });
                } else {
                    this.showToast(res.message || 'Failed to add note.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while saving note.', 'error');
            });
        },

        openEditNoteModal: function (subId, noteId) {
            const notes = (activeDrawerData && activeDrawerData.notes) ? activeDrawerData.notes : [];
            const note = notes.find(n => String(n.id) === String(noteId));
            if (!note) return;

            const html = `
                <div class="subscriptions-modal-header">
                    <h3 class="subscriptions-modal-title">Edit Note</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.subscriptionsApp.submitEditNote(event, '${subId}', '${note.id}')">
                    <div class="subscriptions-modal-body">
                        <div class="form-group" style="margin-bottom:14px;">
                            <label class="form-label">Note *</label>
                            <textarea class="input-control" id="editNoteTextVal" rows="4" style="height:110px;resize:none;" required>${escapeHtml(note.content || note.text || '')}</textarea>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" class="input-checkbox" id="editNotePinVal" ${(note.is_pinned || note.pinned) ? 'checked' : ''}>
                            <label for="editNotePinVal" style="font-size:13px;color:var(--text-heading);cursor:pointer;user-select:none;">Pin this note</label>
                        </div>
                    </div>
                    <div class="subscriptions-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Note</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitEditNote: function (e, subId, noteId) {
            e.preventDefault();
            const text = document.getElementById('editNoteTextVal').value.trim();
            const isPinned = document.getElementById('editNotePinVal').checked ? 1 : 0;
            if (!text) return;

            const fd = new FormData();
            fd.append('action', 'edit_note');
            fd.append('note_id', noteId);
            fd.append('content', text);
            fd.append('is_pinned', isPinned);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.closeModal();
                    this.showToast(res.message || 'Note updated.', 'success');
                    fetchDrawerData(subId, () => {
                        this.switchDrawerTab('notes');
                    });
                } else {
                    this.showToast(res.message || 'Failed to update note.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while updating note.', 'error');
            });
        },

        togglePinNote: function (subId, noteId) {
            const fd = new FormData();
            fd.append('action', 'toggle_pin_note');
            fd.append('note_id', noteId);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.showToast(res.is_pinned ? 'Note pinned to top.' : 'Note unpinned.', 'info');
                    fetchDrawerData(subId, () => {
                        this.switchDrawerTab('notes');
                    });
                } else {
                    this.showToast(res.message || 'Failed to toggle pin.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while toggling pin.', 'error');
            });
        },

        confirmDeleteNote: function (subId, noteId) {
            if (window.showConfirmModal) {
                window.showConfirmModal({
                    title: 'Delete Note?',
                    message: 'Are you sure you want to delete this note? This action cannot be undone.',
                    type: 'danger',
                    confirmText: 'Delete Note',
                    onConfirm: () => {
                        this.executeDeleteNote(subId, noteId);
                    }
                });
            } else {
                const html = `
                    <div class="subscriptions-modal-header">
                        <h3 class="subscriptions-modal-title">Delete Note?</h3>
                        <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="subscriptions-modal-body">
                        <p style="font-size:13.5px;color:var(--text-body);line-height:1.5;margin:0;">
                            Are you sure you want to delete this note? This action cannot be undone.
                        </p>
                    </div>
                    <div class="subscriptions-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Cancel</button>
                        <button type="button" class="btn btn-primary btn-sm" style="background-color:#DC2626;border-color:#DC2626;" onclick="window.subscriptionsApp.executeDeleteNote('${subId}', '${noteId}')">Delete Note</button>
                    </div>
                `;
                this.openModal(html);
            }
        },

        executeDeleteNote: function (subId, noteId) {
            const fd = new FormData();
            fd.append('action', 'delete_note');
            fd.append('note_id', noteId);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.closeModal();
                    this.showToast(res.message || 'Note deleted.', 'info');
                    fetchDrawerData(subId, () => {
                        this.switchDrawerTab('notes');
                    });
                } else {
                    this.showToast(res.message || 'Failed to delete note.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while deleting note.', 'error');
            });
        },

        // Top-Level Child Modals Management
        openModal: function (htmlContent) {
            const overlay = document.getElementById('subActionModal');
            const card = document.getElementById('subActionModalCard');
            if (overlay && card) {
                card.innerHTML = htmlContent;
                overlay.style.display = 'flex';
                overlay.classList.add('show');
            }
        },

        closeModal: function () {
            const overlay = document.getElementById('subActionModal');
            if (overlay) {
                overlay.classList.remove('show');
                overlay.style.display = 'none';
            }
        },

        // New Subscription Modal
        openNewSubscriptionModal: function () {
            const today = new Date().toISOString().split('T')[0];
            const nextMonth = new Date();
            nextMonth.setDate(nextMonth.getDate() + 30);
            const defaultNext = nextMonth.toISOString().split('T')[0];

            let ownersHtml = '';
            if (availableOwners.length > 0) {
                ownersHtml = availableOwners.map(o => `<option value="${o.id}">${escapeHtml(o.name)}</option>`).join('');
            } else {
                ownersHtml = `<option value="1">Account Owner</option>`;
            }

            let plansHtml = availablePlans.map(p => `<option value="${escapeHtml(p)}">${escapeHtml(p)}</option>`).join('');

            const html = `
                <div class="subscriptions-modal-header">
                    <h3 class="subscriptions-modal-title">Create New Subscription</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form id="newSubscriptionForm" onsubmit="window.subscriptionsApp.submitNewSubscription(event)">
                    <div class="subscriptions-modal-body">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Subscription Name *</label>
                                <input type="text" class="input-control" id="newSubName" placeholder="e.g. Enterprise Cloud Suite" required>
                            </div>
                            <div>
                                <label class="form-label">Customer / Company *</label>
                                <input type="text" class="input-control" id="newSubCompany" list="companiesDataList" placeholder="e.g. Acme Corp" required>
                                <datalist id="companiesDataList">
                                    ${availableCompanies.map(c => `<option value="${escapeHtml(c.name)}"></option>`).join('')}
                                </datalist>
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Related Project / Service</label>
                            <input type="text" class="input-control" id="newSubProject" placeholder="e.g. Enterprise Sales Pipeline Expansion">
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Plan Tier *</label>
                                <select class="input-control" id="newSubPlan">
                                    ${plansHtml}
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Billing Cycle *</label>
                                <select class="input-control" id="newSubCycle">
                                    <option value="Monthly">Monthly</option>
                                    <option value="Quarterly">Quarterly</option>
                                    <option value="Yearly">Yearly</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Amount ($) *</label>
                                <input type="number" step="0.01" min="0" class="input-control" id="newSubAmount" placeholder="2500" required>
                            </div>
                            <div>
                                <label class="form-label">Initial Status *</label>
                                <select class="input-control" id="newSubStatus">
                                    <option value="Active">Active</option>
                                    <option value="Trial">Trial</option>
                                    <option value="Paused">Paused</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Start Date *</label>
                                <input type="date" class="input-control" id="newSubStartDate" value="${today}" required>
                            </div>
                            <div>
                                <label class="form-label">Next Billing Date *</label>
                                <input type="date" class="input-control" id="newSubNextDate" value="${defaultNext}" required>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Renewal Type</label>
                                <select class="input-control" id="newSubRenewal">
                                    <option value="Automatic">Automatic</option>
                                    <option value="Manual">Manual</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Owner / Account Lead</label>
                                <select class="input-control" id="newSubOwner">
                                    ${ownersHtml}
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Internal Notes (Optional)</label>
                            <textarea class="input-control" id="newSubNotes" rows="2" style="height:60px;resize:none;" placeholder="Key customer requirements or SLA notes..."></textarea>
                        </div>
                    </div>
                    <div class="subscriptions-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Create Subscription</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitNewSubscription: function (e) {
            e.preventDefault();
            const name = document.getElementById('newSubName').value.trim();
            const company = document.getElementById('newSubCompany').value.trim();
            const project = document.getElementById('newSubProject').value.trim();
            const plan = document.getElementById('newSubPlan').value;
            const cycle = document.getElementById('newSubCycle').value;
            const status = document.getElementById('newSubStatus').value;
            const startDate = document.getElementById('newSubStartDate').value;
            const nextDate = document.getElementById('newSubNextDate').value;
            const renewal = document.getElementById('newSubRenewal').value;
            const ownerId = document.getElementById('newSubOwner').value;
            const notes = document.getElementById('newSubNotes').value.trim();

            if (!name || !company) {
                this.showToast('Please fill all required fields.', 'warning');
                return;
            }

            const amountStr = document.getElementById('newSubAmount')?.value.trim();
            const amount = parseFloat(amountStr);

            if (amountStr === '' || isNaN(amount)) {
                this.showToast('Amount must be a valid numeric value.', 'warning');
                return;
            }

            if (amount < 0) {
                this.showToast('Amount cannot be negative.', 'warning');
                return;
            }

            if (status !== 'Trial' && amount <= 0) {
                this.showToast(`Amount must be greater than 0 for ${status} subscriptions.`, 'warning');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'create');
            fd.append('name', name);
            fd.append('company_name', company);
            fd.append('project_name', project);
            fd.append('plan', plan);
            fd.append('billing_cycle', cycle);
            fd.append('amount', amount);
            fd.append('status', status);
            fd.append('start_date', startDate);
            fd.append('next_billing_date', nextDate);
            fd.append('renewal_type', renewal);
            fd.append('owner_id', ownerId);
            fd.append('notes', notes);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.closeModal();
                    this.showToast(`Subscription "${name}" created successfully.`, 'success');
                    fetchSubscriptions();
                } else {
                    this.showToast(res.message || 'Failed to create subscription.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error creating subscription.', 'error');
            });
        },

        // Edit Subscription Modal
        openEditModal: function (subId) {
            const sub = subscriptions.find(s => String(s.id) === String(subId));
            if (!sub) return;

            let plansHtml = availablePlans.map(p => `<option value="${escapeHtml(p)}" ${p === sub.plan ? 'selected' : ''}>${escapeHtml(p)}</option>`).join('');

            const html = `
                <div class="subscriptions-modal-header">
                    <h3 class="subscriptions-modal-title">Edit Subscription: ${escapeHtml(sub.name)}</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.subscriptionsApp.submitEditSubscription(event, '${sub.id}')">
                    <div class="subscriptions-modal-body">
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Subscription Name</label>
                            <input type="text" class="input-control" id="editSubName" value="${escapeHtml(sub.name)}" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Plan Tier</label>
                                <select class="input-control" id="editSubPlan">
                                    ${plansHtml}
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Billing Cycle</label>
                                <select class="input-control" id="editSubCycle">
                                    <option value="Monthly" ${(sub.billing_cycle === 'Monthly' || sub.billingCycle === 'Monthly') ? 'selected' : ''}>Monthly</option>
                                    <option value="Quarterly" ${(sub.billing_cycle === 'Quarterly' || sub.billingCycle === 'Quarterly') ? 'selected' : ''}>Quarterly</option>
                                    <option value="Yearly" ${(sub.billing_cycle === 'Yearly' || sub.billingCycle === 'Yearly') ? 'selected' : ''}>Yearly</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Amount ($)</label>
                                <input type="number" step="0.01" min="0" class="input-control" id="editSubAmount" value="${sub.amount}" required>
                            </div>
                            <div>
                                <label class="form-label">Status</label>
                                <select class="input-control" id="editSubStatus">
                                    <option value="Active" ${sub.status === 'Active' ? 'selected' : ''}>Active</option>
                                    <option value="Trial" ${sub.status === 'Trial' ? 'selected' : ''}>Trial</option>
                                    <option value="Past Due" ${sub.status === 'Past Due' ? 'selected' : ''}>Past Due</option>
                                    <option value="Paused" ${sub.status === 'Paused' ? 'selected' : ''}>Paused</option>
                                    <option value="Cancelled" ${sub.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Next Billing Date</label>
                            <input type="date" class="input-control" id="editSubNextDate" value="${sub.next_billing_date || sub.nextBillingDate || ''}">
                        </div>
                    </div>
                    <div class="subscriptions-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitEditSubscription: function (e, subId) {
            e.preventDefault();
            const name = document.getElementById('editSubName').value.trim();
            const plan = document.getElementById('editSubPlan').value;
            const cycle = document.getElementById('editSubCycle').value;
            const status = document.getElementById('editSubStatus').value;
            const nextDate = document.getElementById('editSubNextDate').value;

            if (!name) {
                this.showToast('Subscription name is required.', 'warning');
                return;
            }

            const amountStr = document.getElementById('editSubAmount')?.value.trim();
            const amount = parseFloat(amountStr);

            if (amountStr === '' || isNaN(amount)) {
                this.showToast('Amount must be a valid numeric value.', 'warning');
                return;
            }

            if (amount < 0) {
                this.showToast('Amount cannot be negative.', 'warning');
                return;
            }

            if (status !== 'Trial' && amount <= 0) {
                this.showToast(`Amount must be greater than 0 for ${status} subscriptions.`, 'warning');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'update');
            fd.append('id', subId);
            fd.append('name', name);
            fd.append('plan', plan);
            fd.append('billing_cycle', cycle);
            fd.append('amount', amount);
            fd.append('status', status);
            fd.append('next_billing_date', nextDate);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.closeModal();
                    this.showToast('Subscription updated successfully.', 'success');
                    fetchSubscriptions();
                    if (activeSubId === subId) {
                        this.openDrawer(subId);
                    }
                } else {
                    this.showToast(res.message || 'Failed to update subscription.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while saving changes.', 'error');
            });
        },

        // Pause/Resume Toggle
        togglePause: function (subId) {
            const fd = new FormData();
            fd.append('action', 'toggle_pause');
            fd.append('id', subId);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.showToast(res.message || `Subscription status updated.`, 'info');
                    fetchSubscriptions();
                    if (activeSubId === subId) {
                        this.openDrawer(subId);
                    }
                } else {
                    this.showToast(res.message || 'Failed to toggle pause.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while toggling pause.', 'error');
            });
        },

        // Change Plan Modal
        openChangePlanModal: function (subId) {
            const sub = subscriptions.find(s => String(s.id) === String(subId));
            if (!sub) return;

            const planOptions = [
                { name: 'Starter Cloud', seats: 'Up to 10 Seats • Core CRM', amount: 850 },
                { name: 'Growth Plan', seats: 'Up to 50 Seats • Pipeline Automation', amount: 2400 },
                { name: 'Professional Suite', seats: 'Up to 100 Seats • Full Analytics', amount: 3500 },
                { name: 'Enterprise Tier', seats: 'Unlimited Seats • 24/7 SLA Support', amount: 4850 }
            ];

            const html = `
                <div class="subscriptions-modal-header">
                    <h3 class="subscriptions-modal-title">Change Plan Tier</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="subscriptions-modal-body">
                    <p style="font-size:13px;color:var(--text-secondary);margin-top:0;">Select a new plan tier for <strong>${escapeHtml(sub.name)}</strong> (${escapeHtml(sub.company_name || sub.company || '')}).</p>
                    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
                        ${planOptions.map(p => `
                            <label style="display:flex;align-items:center;justify-content:space-between;padding:12px;border:1px solid var(--border-divider);border-radius:8px;cursor:pointer;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <input type="radio" name="changePlanOption" value="${p.name}" data-amount="${p.amount}" ${sub.plan === p.name ? 'checked' : ''}>
                                    <div>
                                        <strong style="font-size:13.5px;color:var(--text-heading);">${p.name}</strong>
                                        <div style="font-size:11.5px;color:var(--text-secondary);">${p.seats}</div>
                                    </div>
                                </div>
                                <span style="font-weight:700;">$${formatCurrency(p.amount)} / mo</span>
                            </label>
                        `).join('')}
                    </div>
                </div>
                <div class="subscriptions-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.subscriptionsApp.confirmChangePlanFromRadio('${sub.id}')">Apply Plan</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmChangePlanFromRadio: function (subId) {
            const selected = document.querySelector('input[name="changePlanOption"]:checked');
            if (!selected) {
                this.showToast('Please select a plan.', 'warning');
                return;
            }
            const newPlan = selected.value;
            const newAmount = parseFloat(selected.getAttribute('data-amount')) || 0;
            this.confirmChangePlan(subId, newPlan, newAmount);
        },

        confirmChangePlan: function (subId, newPlan, newAmount) {
            const fd = new FormData();
            fd.append('action', 'change_plan');
            fd.append('id', subId);
            fd.append('plan', newPlan);
            fd.append('amount', newAmount);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.closeModal();
                    this.showToast(res.message || `Plan updated to ${newPlan}.`, 'success');
                    fetchSubscriptions();
                    if (activeSubId === subId) {
                        this.openDrawer(subId);
                    }
                } else {
                    this.showToast(res.message || 'Failed to update plan.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while updating plan.', 'error');
            });
        },

        // Record Payment Modal
        openRecordPaymentModal: function (subId) {
            const sub = (activeDrawerData && activeDrawerData.subscription) ? activeDrawerData.subscription : (subscriptions.find(s => String(s.id) === String(subId)) || {});
            if (!sub || !sub.id) return;

            const today = new Date().toISOString().split('T')[0];
            const payMethod = sub.payment_method || sub.paymentMethod || 'Credit Card';

            const html = `
                <div class="subscriptions-modal-header">
                    <h3 class="subscriptions-modal-title">Record Payment: ${escapeHtml(sub.company_name || sub.company || '')}</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.subscriptionsApp.submitRecordPayment(event, '${sub.id}')">
                    <div class="subscriptions-modal-body">
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Payment Amount ($) *</label>
                            <input type="number" step="0.01" class="input-control" id="payAmount" value="${sub.amount || 0}" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Payment Method</label>
                                <select class="input-control" id="payMethod">
                                    <option value="${escapeHtml(payMethod)}">${escapeHtml(payMethod)}</option>
                                    <option value="Credit Card">Credit Card</option>
                                    <option value="ACH Bank Wire">ACH Bank Wire</option>
                                    <option value="Check / Manual Transfer">Check / Manual Transfer</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Payment Date</label>
                                <input type="date" class="input-control" id="payDate" value="${today}" required>
                            </div>
                        </div>
                    </div>
                    <div class="subscriptions-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Record Payment</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitRecordPayment: function (e, subId) {
            e.preventDefault();
            const amount = parseFloat(document.getElementById('payAmount').value) || 0;
            const method = document.getElementById('payMethod').value;
            const date = document.getElementById('payDate').value;

            if (amount <= 0) {
                this.showToast('Please enter a valid payment amount.', 'warning');
                return;
            }

            const fd = new FormData();
            fd.append('action', 'record_payment');
            fd.append('subscription_id', subId);
            fd.append('amount', amount);
            fd.append('payment_method', method);
            fd.append('payment_date', date);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.closeModal();
                    this.showToast(res.message || `Payment recorded.`, 'success');
                    fetchSubscriptions();
                    if (activeSubId === subId) {
                        this.openDrawer(subId);
                    }
                } else {
                    this.showToast(res.message || 'Failed to record payment.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while recording payment.', 'error');
            });
        },

        // Invoice Preview
        previewInvoice: function (invId, subId) {
            const invoices = (activeDrawerData && activeDrawerData.invoices) ? activeDrawerData.invoices : [];
            const inv = invoices.find(i => String(i.id) === String(invId) || String(i.invoice_number) === String(invId));
            const sub = (activeDrawerData && activeDrawerData.subscription) ? activeDrawerData.subscription : (subscriptions.find(s => String(s.id) === String(subId)) || {});
            if (!inv) return;

            const invCode = inv.invoice_number || inv.id;
            const companyName = sub.company_name || sub.company || 'Customer';

            const html = `
                <div class="subscriptions-modal-header">
                    <h3 class="subscriptions-modal-title">Invoice #${escapeHtml(invCode)}</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="subscriptions-modal-body">
                    <div style="background:#F8FAFC;padding:24px;border:1px solid var(--border-divider);border-radius:8px;margin-bottom:16px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
                            <div>
                                <h4 style="font-size:16px;font-weight:700;color:var(--text-heading);margin:0;">NexFlow CRM</h4>
                                <p style="font-size:12px;color:var(--text-secondary);margin:2px 0 0 0;">Billing &amp; Customer Subscriptions</p>
                            </div>
                            <span class="badge-sub ${inv.status === 'Paid' ? 'badge-active' : 'badge-pastdue'}">${escapeHtml(inv.status)}</span>
                        </div>
                        <div style="font-size:13px;color:var(--text-body);line-height:1.7;">
                            <strong>Invoice Number:</strong> ${escapeHtml(invCode)}<br>
                            <strong>Billed To:</strong> ${escapeHtml(companyName)}<br>
                            <strong>Subscription:</strong> ${escapeHtml(sub.name || '')} (${escapeHtml(sub.plan || '')})<br>
                            <strong>Invoice Date:</strong> ${escapeHtml(inv.invoice_date || inv.date || '—')}<br>
                            <strong>Billing Period:</strong> ${escapeHtml(sub.billing_cycle || sub.billingCycle || 'Monthly')}<br>
                            <strong>Payment Method:</strong> ${escapeHtml(inv.payment_method || sub.payment_method || sub.paymentMethod || 'Credit Card')}<br>
                            <strong>Total Amount Due:</strong> <strong style="color:var(--text-heading);">$${formatCurrency(inv.amount)}</strong>
                        </div>
                    </div>
                </div>
                <div class="subscriptions-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">Print Invoice</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Close</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.subscriptionsApp.downloadDemoInvoice('${escapeHtml(invCode)}', '${escapeHtml(companyName)}')">Download Summary</button>
                </div>
            `;
            this.openModal(html);
        },

        downloadDemoInvoice: function (invId, company) {
            const textContent = `NexFlow CRM INVOICE SUMMARY\nInvoice #: ${invId}\nCompany: ${company || 'Customer'}\nDate: ${new Date().toISOString().split('T')[0]}\nStatus: Paid\nThank you for your business!`;
            const blob = new Blob([textContent], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Invoice_${invId}.txt`;
            a.click();
            window.URL.revokeObjectURL(url);
            this.showToast(`Invoice summary ${invId} downloaded.`, 'success');
        },

        // Cancel Modal
        openCancelModal: function (subId) {
            const sub = subscriptions.find(s => String(s.id) === String(subId));
            if (!sub) return;

            const html = `
                <div class="subscriptions-modal-header">
                    <h3 class="subscriptions-modal-title">Cancel Subscription</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="subscriptions-modal-body">
                    <p style="font-size:13.5px;color:var(--text-body);line-height:1.5;">
                        Are you sure you want to cancel <strong>${escapeHtml(sub.name)}</strong> for <strong>${escapeHtml(sub.company_name || sub.company || '')}</strong>?
                    </p>
                    <p style="font-size:12.5px;color:var(--text-secondary);">
                        This will set the status to Cancelled and halt automatic renewals.
                    </p>
                </div>
                <div class="subscriptions-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Keep Active</button>
                    <button type="button" class="btn btn-primary btn-sm" style="background-color:#DC2626;border-color:#DC2626;" onclick="window.subscriptionsApp.confirmCancel('${sub.id}')">Cancel Subscription</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmCancel: function (subId) {
            const fd = new FormData();
            fd.append('action', 'cancel');
            fd.append('id', subId);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    this.closeModal();
                    this.showToast(res.message || 'Subscription cancelled.', 'warning');
                    fetchSubscriptions();
                    if (activeSubId === subId) {
                        this.openDrawer(subId);
                    }
                } else {
                    this.showToast(res.message || 'Failed to cancel subscription.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while cancelling subscription.', 'error');
            });
        },

        // Delete Modal
        openDeleteModal: function (subId) {
            const sub = subscriptions.find(s => String(s.id) === String(subId));
            if (!sub) return;

            const html = `
                <div class="subscriptions-modal-header">
                    <h3 class="subscriptions-modal-title">Delete Subscription</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.subscriptionsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="subscriptions-modal-body">
                    <p style="font-size:13.5px;color:var(--text-body);line-height:1.5;">
                        Are you sure you want to remove the record for <strong>${escapeHtml(sub.name)}</strong>?
                    </p>
                </div>
                <div class="subscriptions-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.subscriptionsApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" style="background-color:#DC2626;border-color:#DC2626;" onclick="window.subscriptionsApp.confirmDelete('${sub.id}')">Delete</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmDelete: function (subId) {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', subId);

            fetch('api/subscriptions.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    selectedIds.delete(subId);
                    this.closeModal();
                    if (activeSubId === subId) {
                        this.closeDrawer();
                    }
                    this.showToast(res.message || 'Subscription record removed.', 'info');
                    fetchSubscriptions();
                } else {
                    this.showToast(res.message || 'Failed to delete subscription.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                this.showToast('Network error while deleting subscription.', 'error');
            });
        },

        // Export CSV
        exportCSV: function () {
            const params = new URLSearchParams({
                action: 'export_csv',
                search: currentSearch,
                kpi_filter: currentKpiFilter,
                status: currentFilterStatus,
                plan: currentFilterPlan,
                billing_cycle: currentFilterCycle,
                sort: currentSort
            });
            window.location.href = 'api/subscriptions.php?' + params.toString();
        },

        // Toast Messages Root
        showToast: function (msg, type = 'info') {
            const container = document.getElementById('subscriptionsToastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `subscriptions-toast ${type}`;
            toast.innerHTML = `<span>${escapeHtml(msg)}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.25s ease';
                setTimeout(() => toast.remove(), 250);
            }, 3200);
        }
    };

    // Auto-init on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.subscriptionsApp.init());
    } else {
        window.subscriptionsApp.init();
    }
})();