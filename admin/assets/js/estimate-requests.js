/**
 * NexFlow CRM — Estimate Requests Management JavaScript Controller
 * Connected to MySQL database via admin/api/estimate-requests.php.
 * Multi-tenant, zero mock/demo records, zero client-side business storage.
 */

(function () {
    'use strict';

    const API_BASE = 'api/estimate-requests.php';

    let requests = [];
    let referenceOptions = {
        companies: [],
        contacts: [],
        users: [],
        deals: [],
        statuses: ['New', 'In Review', 'More Info Needed', 'Approved', 'Rejected', 'Converted'],
        services: []
    };

    let currentFilterTab = 'All';
    let currentServiceFilter = 'All';
    let currentSearch = '';
    let currentSort = 'newest';
    let currentPage = 1;
    let pageSize = 10;
    let totalItems = 0;
    let totalPages = 1;
    let activeRequestId = null;
    let activeRecord = null;
    let estNoteSearchQuery = '';
    let selectedIds = new Set();
    let searchDebounceTimer = null;

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getPaginationPages(curr, total) {
        if (total <= 7) {
            const pages = [];
            for (let i = 1; i <= total; i++) pages.push(i);
            return pages;
        }
        if (curr <= 4) return [1, 2, 3, 4, 5, '...', total];
        if (curr >= total - 3) return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
        return [1, '...', curr - 1, curr, curr + 1, '...', total];
    }

    // Public Controller Window Binding
    window.estimateRequestsApp = {
        init: function () {
            const urlParams = new URLSearchParams(window.location.search);
            const initialSearch = urlParams.get('search') || urlParams.get('q');
            const searchInput = document.getElementById('reqSearchInput');
            if (initialSearch) {
                currentSearch = initialSearch.trim();
                if (searchInput) searchInput.value = currentSearch;
            } else if (searchInput && searchInput.value.trim()) {
                currentSearch = searchInput.value.trim();
            }

            const initialStatus = urlParams.get('status');
            if (initialStatus) {
                currentFilterTab = initialStatus;
                const statusSelect = document.getElementById('reqStatusSelect');
                if (statusSelect) statusSelect.value = currentFilterTab;
            }

            this.setupEventListeners();
            this.loadReferenceOptions();
            this.fetchSummary();
            this.fetchRequests();
        },

        setupEventListeners: function () {
            const searchInput = document.getElementById('reqSearchInput');
            if (searchInput) {
                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        window.estimateRequestsApp.handleSearch(this.value, true);
                    }
                });
            }

            document.addEventListener('click', function (e) {
                const popover = document.getElementById('estimateRequestsFilterPopover');
                const btn = document.getElementById('btnEstReqFilter');
                if (popover && popover.classList.contains('show')) {
                    if (!popover.contains(e.target) && (!btn || !btn.contains(e.target))) {
                        window.estimateRequestsApp.closeFilterPopover();
                    }
                }

                const menu = document.getElementById('estimateRequestsRowDropdown');
                if (menu && !e.target.closest('#estimateRequestsRowDropdown') && !e.target.closest('.est-row-menu-btn')) {
                    menu.classList.remove('show');
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('estActionModal');
                    if (modal && modal.classList.contains('show')) {
                        window.estimateRequestsApp.closeModal();
                        e.stopPropagation();
                        return;
                    }
                    const drawer = document.getElementById('estimateRequestsDrawer');
                    if (drawer && drawer.classList.contains('show')) {
                        window.estimateRequestsApp.closeDrawer();
                        e.stopPropagation();
                        return;
                    }
                    const popover = document.getElementById('estimateRequestsFilterPopover');
                    if (popover && popover.classList.contains('show')) {
                        window.estimateRequestsApp.closeFilterPopover();
                        e.stopPropagation();
                        return;
                    }
                }
            });
        },

        // 1. Fetch Reference Options (Companies, Contacts, Users)
        loadReferenceOptions: function () {
            fetch(`${API_BASE}?action=reference_options`)
                .then(res => res.json())
                .then(json => {
                    if (json.success && json.data) {
                        referenceOptions = json.data;
                    }
                })
                .catch(err => console.error('Failed to load reference options:', err));
        },

        // 2. Fetch Live Summary & KPIs
        fetchSummary: function () {
            fetch(`${API_BASE}?action=summary`)
                .then(res => res.json())
                .then(json => {
                    if (json.success && json.data) {
                        const s = json.data;

                        const badge = document.getElementById('requestsTotalCountBadge');
                        if (badge) badge.textContent = `${s.total} requests`;

                        const elTotal = document.getElementById('kpiTotalRequests');
                        if (elTotal) elTotal.textContent = s.total;

                        const elNew = document.getElementById('kpiNewRequests');
                        if (elNew) elNew.textContent = s.new;

                        const elReview = document.getElementById('kpiInReview');
                        if (elReview) elReview.textContent = s.in_review;

                        const elApproved = document.getElementById('kpiApproved');
                        if (elApproved) elApproved.textContent = s.approved;

                        const elConverted = document.getElementById('kpiConverted');
                        if (elConverted) elConverted.textContent = s.converted;

                        // Update Status dropdown option counts
                        const optAll = document.getElementById('optEstStatus_All');
                        if (optAll) optAll.textContent = `All Statuses (${s.total})`;

                        const optNew = document.getElementById('optEstStatus_New');
                        if (optNew) optNew.textContent = `New (${s.new})`;

                        const optReview = document.getElementById('optEstStatus_InReview');
                        if (optReview) optReview.textContent = `In Review (${s.in_review})`;

                        const optMore = document.getElementById('optEstStatus_MoreInfoNeeded');
                        if (optMore) optMore.textContent = `More Info Needed (${s.more_info_needed})`;

                        const optAppr = document.getElementById('optEstStatus_Approved');
                        if (optAppr) optAppr.textContent = `Approved (${s.approved})`;

                        const optRej = document.getElementById('optEstStatus_Rejected');
                        if (optRej) optRej.textContent = `Rejected (${s.rejected})`;

                        const optConv = document.getElementById('optEstStatus_Converted');
                        if (optConv) optConv.textContent = `Converted (${s.converted})`;
                    }
                })
                .catch(err => console.error('Failed to fetch summary:', err));
        },

        // 3. Fetch List from MySQL API
        fetchRequests: function () {
            const params = new URLSearchParams({
                action: 'list',
                search: currentSearch,
                status: currentFilterTab,
                service: currentServiceFilter,
                sort: currentSort,
                page: currentPage,
                per_page: pageSize
            });

            fetch(`${API_BASE}?${params.toString()}`)
                .then(res => res.json())
                .then(json => {
                    if (json.success && json.data) {
                        requests = json.data.requests || [];
                        totalItems = json.data.total || 0;
                        currentPage = json.data.page || 1;
                        pageSize = json.data.per_page || 10;
                        totalPages = json.data.total_pages || 1;
                        this.renderTable();
                    } else {
                        requests = [];
                        totalItems = 0;
                        totalPages = 1;
                        this.renderTable();
                    }
                })
                .catch(err => {
                    console.error('Failed to fetch requests:', err);
                    requests = [];
                    totalItems = 0;
                    totalPages = 1;
                    this.renderTable();
                });
        },

        // 4. Render Table Rows
        renderTable: function () {
            const tbody = document.getElementById('estimateRequestsTableBody');
            if (!tbody) return;

            const startIndex = (currentPage - 1) * pageSize;
            const pagingRange = document.getElementById('pagingRange');
            const pagingTotal = document.getElementById('pagingTotal');
            const controls = document.getElementById('paginationControls');

            if (pagingRange) {
                pagingRange.textContent = totalItems > 0 ? `${startIndex + 1}–${Math.min(startIndex + pageSize, totalItems)}` : '0–0';
            }
            if (pagingTotal) {
                pagingTotal.textContent = totalItems;
            }

            const container = document.querySelector('.pagination-container');
            if (container) {
                container.style.display = totalItems > 0 ? 'flex' : 'none';
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
                        <button class="pagination-btn" id="prevPageBtn" ${currentPage === 1 ? 'disabled' : ''} onclick="window.estimateRequestsApp.goToPage(${currentPage - 1})">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                    `;

                    const pageNumbers = getPaginationPages(currentPage, totalPages);
                    pageNumbers.forEach(p => {
                        if (p === '...') {
                            html += `<span class="pagination-btn" style="border:none;background:none;cursor:default;">...</span>`;
                        } else {
                            const isActive = (p === currentPage);
                            html += `<button type="button" class="pagination-btn ${isActive ? 'active' : ''}" onclick="window.estimateRequestsApp.goToPage(${p})">${p}</button>`;
                        }
                    });

                    html += `
                        <button class="pagination-btn" id="nextPageBtn" ${currentPage === totalPages ? 'disabled' : ''} onclick="window.estimateRequestsApp.goToPage(${currentPage + 1})">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    `;
                    controls.innerHTML = html;
                }
            }

            if (requests.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="11" style="text-align:center;padding:36px;color:var(--text-secondary);">
                            No estimate requests match current search or filter criteria.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = requests.map(r => {
                const statusBadge = r.status === 'New' ? 'badge-new' :
                                    r.status === 'In Review' ? 'badge-inreview' :
                                    r.status === 'More Info Needed' ? 'badge-moreinfo' :
                                    r.status === 'Approved' ? 'badge-approved' :
                                    r.status === 'Rejected' ? 'badge-rejected' : 'badge-converted';

                const ownerInitials = (r.owner || 'NA').split(' ').map(n => n[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();

                return `
                    <tr data-req-id="${escapeHtml(r.id)}">
                        <td style="font-weight:600;color:var(--text-heading);font-size:12.5px;">${escapeHtml(r.id)}</td>
                        <td>
                            <div style="font-weight:600;color:var(--text-heading);font-size:13.5px;cursor:pointer;" onclick="window.estimateRequestsApp.openDrawer('${escapeHtml(r.id)}')">
                                ${escapeHtml(r.subject)}
                            </div>
                            <div style="font-size:11.5px;color:var(--text-secondary);margin-top:2px;">
                                Source: ${escapeHtml(r.source)}
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600;color:var(--text-heading);">${escapeHtml(r.company)}</div>
                        </td>
                        <td>
                            <div style="font-weight:500;color:var(--text-heading);">${escapeHtml(r.contact)}</div>
                            <div style="font-size:11px;color:var(--text-secondary);">${escapeHtml(r.email)}</div>
                        </td>
                        <td>
                            <span style="font-size:12px;color:#334155;">${escapeHtml(r.services)}</span>
                        </td>
                        <td style="font-weight:600;color:var(--text-heading);">${escapeHtml(r.budget)}</td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <div class="avatar avatar-xs" style="background-color:#7C3AED;font-size:9px;">${escapeHtml(ownerInitials)}</div>
                                <span>${escapeHtml(r.owner)}</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge-req ${statusBadge}">● ${escapeHtml(r.status)}</span>
                        </td>
                        <td>${escapeHtml(r.createdDate)}</td>
                        <td style="font-size:12px;color:var(--text-secondary);">${escapeHtml(r.lastActivity)}</td>
                        <td style="text-align:right;white-space:nowrap;">
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.estimateRequestsApp.openDrawer('${r.id}')">Details</button>
                        </td>
                    </tr>
                `;
            }).join('');
        },

        toggleSelection: function (id, isChecked) {
            if (isChecked) selectedIds.add(id);
            else selectedIds.delete(id);
            this.renderTable();
        },

        goToPage: function (p) {
            if (p < 1 || p > totalPages || p === currentPage) return;
            currentPage = p;
            this.fetchRequests();
        },

        filterByStatus: function (statusVal) {
            currentFilterTab = statusVal || 'All';
            currentPage = 1;
            const select = document.getElementById('reqStatusSelect');
            if (select && select.value !== currentFilterTab) {
                select.value = currentFilterTab;
            }
            this.fetchRequests();
        },

        filterByTab: function (tabName) {
            this.filterByStatus(tabName);
        },

        filterByKPI: function (statusName) {
            this.filterByStatus(statusName);
        },

        handleSearch: function (query, immediate = false) {
            clearTimeout(searchDebounceTimer);
            const doSearch = () => {
                currentSearch = (query || '').trim();
                currentPage = 1;
                this.fetchRequests();
            };
            if (immediate) {
                doSearch();
            } else {
                searchDebounceTimer = setTimeout(doSearch, 250);
            }
        },

        handleSort: function (sortVal) {
            currentSort = sortVal;
            currentPage = 1;
            this.fetchRequests();
        },

        // Details Drawer
        openDrawer: function (reqId) {
            activeRequestId = reqId;
            const drawer = document.getElementById('estimateRequestsDrawer');
            if (!drawer) return;

            drawer.style.display = 'block';
            drawer.classList.add('show');
            document.body.style.overflow = 'hidden';

            const title = document.getElementById('estDrawerTitle');
            const subtitle = document.getElementById('estDrawerSubtitle');
            const badge = document.getElementById('estDrawerBadge');
            const body = document.getElementById('estDrawerBody');

            if (title) title.textContent = 'Loading request...';
            if (subtitle) subtitle.textContent = '';
            if (body) body.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-secondary);">Loading details...</div>';

            fetch(`${API_BASE}?action=get&id=${encodeURIComponent(reqId)}`)
                .then(res => res.json())
                .then(json => {
                    if (!json.success || !json.data) {
                        this.showToast(json.message || 'Request not found.', 'error');
                        this.closeDrawer();
                        return;
                    }

                    activeRecord = json.data;
                    if (title) title.textContent = activeRecord.subject;
                    if (subtitle) subtitle.textContent = `${activeRecord.company} • ${activeRecord.id}`;
                    if (badge) {
                        badge.className = `badge-req badge-${activeRecord.status.toLowerCase().replace(/\s+/g, '')}`;
                        badge.textContent = `● ${activeRecord.status}`;
                    }

                    this.switchDrawerTab('overview');
                })
                .catch(err => {
                    console.error('Failed to load request details:', err);
                    this.showToast('Failed to load details.', 'error');
                });
        },

        closeDrawer: function () {
            const drawer = document.getElementById('estimateRequestsDrawer');
            if (drawer) {
                drawer.classList.remove('show');
                drawer.style.display = 'none';
                document.body.style.overflow = '';
            }
            activeRequestId = null;
            activeRecord = null;
        },

        switchDrawerTab: function (tabName) {
            document.querySelectorAll('.est-req-drawer-tab').forEach(t => {
                t.classList.toggle('active', t.getAttribute('data-tab') === tabName);
            });

            const body = document.getElementById('estDrawerBody');
            if (!body || !activeRecord) return;

            const req = activeRecord;

            if (tabName === 'overview') {
                body.innerHTML = `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid var(--border-divider);">
                            <div style="font-size:11px;color:var(--text-secondary);font-weight:600;">ESTIMATED BUDGET</div>
                            <div style="font-size:16px;font-weight:700;color:var(--text-heading);margin-top:2px;">${escapeHtml(req.budget)}</div>
                        </div>
                        <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid var(--border-divider);">
                            <div style="font-size:11px;color:var(--text-secondary);font-weight:600;">EXPECTED TIMELINE</div>
                            <div style="font-size:15px;font-weight:700;color:var(--text-heading);margin-top:4px;">${escapeHtml(req.timeline)}</div>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:12px;font-size:13px;border-bottom:1px solid var(--border-divider);padding-bottom:18px;margin-bottom:18px;">
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--text-secondary);">Customer Company:</span>
                            <strong style="color:var(--text-heading);">${escapeHtml(req.company)}</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--text-secondary);">Primary Contact:</span>
                            <strong style="color:var(--text-heading);">${escapeHtml(req.contact)}${req.phone ? ' (' + escapeHtml(req.phone) + ')' : ''}</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--text-secondary);">Contact Email:</span>
                            <strong style="color:var(--text-heading);">${escapeHtml(req.email || 'N/A')}</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--text-secondary);">Requested Services:</span>
                            <strong style="color:var(--text-heading);">${escapeHtml(req.services)}</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--text-secondary);">Request Source:</span>
                            <strong style="color:var(--text-heading);">${escapeHtml(req.source)}</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--text-secondary);">Assigned NexFlow Owner:</span>
                            <strong style="color:var(--text-heading);">${escapeHtml(req.owner)}</strong>
                        </div>
                        ${(req.deal_id || req.deal_code || req.deal_name) ? `
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="color:var(--text-secondary);">Linked Pipeline Deal:</span>
                            <a href="pipeline.php" style="color:var(--primary);font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                                ${escapeHtml(req.deal_name || req.deal_code || 'View Deal')}
                                ${req.deal_code && req.deal_name ? `(${escapeHtml(req.deal_code)})` : ''}
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            </a>
                        </div>
                        ` : ''}
                    </div>
                    <div style="margin-bottom:18px;">
                        <h4 style="font-size:13px;font-weight:700;color:var(--text-heading);margin-bottom:6px;">Summary Description</h4>
                        <p style="font-size:13px;color:var(--text-body);line-height:1.5;margin:0;">${escapeHtml(req.description) || 'No description provided.'}</p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.estimateRequestsApp.openEditModal('${escapeHtml(req.id)}')">Edit Request</button>
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.estimateRequestsApp.openChangeStatusModal('${escapeHtml(req.id)}')">Change Status</button>
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.estimateRequestsApp.openAssignOwnerModal('${escapeHtml(req.id)}')">Assign Owner</button>
                        ${(req.status !== 'Converted' && !req.deal_id) ? `
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.estimateRequestsApp.openConvertToDealModal('${escapeHtml(req.id)}')">Convert to Deal</button>
                        ` : `
                            <a href="pipeline.php" class="btn btn-secondary btn-xs" style="text-decoration:none;display:inline-flex;align-items:center;gap:4px;">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                View Deal
                            </a>
                        `}
                        ${req.status !== 'Approved' && req.status !== 'Converted' ? `
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.estimateRequestsApp.openCreateEstimateModal('${escapeHtml(req.id)}')">Issue Estimate</button>
                        ` : ''}
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.estimateRequestsApp.duplicateRequest('${escapeHtml(req.id)}')">Duplicate</button>
                        <button type="button" class="btn btn-ghost btn-xs" style="color:#DC2626;" onclick="window.estimateRequestsApp.openDeleteModal('${escapeHtml(req.id)}')">Delete</button>
                    </div>
                `;
            } else if (tabName === 'requirements') {
                body.innerHTML = `
                    <div style="margin-bottom:16px;">
                        <h4 style="font-size:13px;font-weight:700;color:var(--text-heading);margin-bottom:6px;">Scope &amp; Deliverables</h4>
                        <p style="font-size:13px;color:var(--text-body);line-height:1.5;margin:0;">${escapeHtml(req.requirements) || 'No scope deliverables specified.'}</p>
                    </div>
                    <div style="margin-bottom:16px;">
                        <h4 style="font-size:13px;font-weight:700;color:var(--text-heading);margin-bottom:6px;">Preferred Start Date</h4>
                        <div style="font-size:13.5px;color:var(--text-body);font-weight:600;">${escapeHtml(req.preferredStartDate || 'Not specified')}</div>
                    </div>
                    <div>
                        <h4 style="font-size:13px;font-weight:700;color:var(--text-heading);margin-bottom:6px;">Attached Specifications</h4>
                        ${(req.attachments && req.attachments.length > 0) ? `
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                ${req.attachments.map(att => `
                                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#F8FAFC;border:1px solid var(--border-divider);border-radius:6px;">
                                        <div style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;">
                                            <svg width="16" height="16" fill="none" stroke="var(--primary)" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                                            <span>${escapeHtml(typeof att === 'string' ? att : (att.name || 'Attachment'))}</span>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        ` : '<p style="font-size:13px;color:var(--text-secondary);margin:0;">No external files attached.</p>'}
                    </div>
                `;
            } else if (tabName === 'activity') {
                const logs = req.timelineLog || [];
                body.innerHTML = `
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                        <h4 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0;">Activity History</h4>
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.estimateRequestsApp.logActivityPrompt('${escapeHtml(req.id)}')">+ Log Activity</button>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:14px;">
                        ${logs.length > 0 ? logs.map(t => `
                            <div style="display:flex;gap:10px;font-size:13px;">
                                <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);margin-top:5px;flex-shrink:0;"></div>
                                <div>
                                    <div style="font-weight:600;color:var(--text-heading);">${escapeHtml(t.user)} <span style="font-size:11px;font-weight:400;color:var(--text-secondary);">• ${escapeHtml(t.date)}</span></div>
                                    <div style="color:var(--text-body);margin-top:2px;">${escapeHtml(t.text)}</div>
                                </div>
                            </div>
                        `).join('') : '<p style="font-size:13px;color:var(--text-secondary);margin:0;">No activity recorded yet.</p>'}
                    </div>
                `;
            } else if (tabName === 'notes') {
                body.innerHTML = this.renderNotesTab(req);
            }
        },

        renderNotesTab: function (req) {
            const notes = req.notes || [];
            const query = (estNoteSearchQuery || '').toLowerCase().trim();

            const filteredNotes = notes.filter(n => {
                if (!query) return true;
                const matchContent = (n.text || n.content || '').toLowerCase().includes(query);
                const matchAuthor = (n.author || '').toLowerCase().includes(query);
                return matchContent || matchAuthor;
            });

            const pinned = filteredNotes.filter(n => n.pinned);
            const recent = filteredNotes.filter(n => !n.pinned);

            return `
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                    <div style="position:relative;flex:1;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#94A3B8;pointer-events:none;">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" class="input-control input-sm" id="estNoteSearchInput" value="${escapeHtml(estNoteSearchQuery)}" placeholder="Search notes..." style="padding-left:30px;width:100%;height:34px;" oninput="window.estimateRequestsApp.onNoteSearchInput(this.value, '${escapeHtml(req.id)}')">
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" style="height:34px;flex-shrink:0;" onclick="window.estimateRequestsApp.openAddNoteModal('${escapeHtml(req.id)}')">
                        + Add Note
                    </button>
                </div>

                <div id="estNotesListContainer">
                    ${this.renderNotesListHtml(req, pinned, recent, filteredNotes.length, notes.length)}
                </div>
            `;
        },

        renderNotesListHtml: function (req, pinned, recent, filteredCount, totalCount) {
            if (totalCount === 0) {
                return `
                    <div class="exp-empty-state" style="text-align:center;padding:48px 20px;">
                        <div class="exp-empty-icon" style="width:44px;height:44px;border-radius:50%;background:#F1F5F9;color:#94A3B8;display:flex;align-items:center;justify-content:center;margin:0 auto 12px auto;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <p class="exp-empty-title" style="font-size:15px;font-weight:600;color:#0F172A;margin:0 0 6px 0;">No notes yet</p>
                        <p class="exp-empty-desc" style="font-size:13px;color:#94A3B8;margin:0;line-height:1.5;">Add an internal note to keep track of important estimate request details.</p>
                    </div>
                `;
            }

            if (filteredCount === 0) {
                return `
                    <div class="exp-empty-state" style="text-align:center;padding:36px 16px;">
                        <p class="exp-empty-title" style="font-size:14px;font-weight:600;color:#0F172A;margin-bottom:4px;">No notes found</p>
                        <p class="exp-empty-desc" style="font-size:12.5px;color:#94A3B8;margin:0;">Try a different search term.</p>
                    </div>
                `;
            }

            let html = '';
            if (pinned.length > 0) {
                html += `
                    <div class="task-group-title" style="color:#2563EB;margin-top:0;">📌 Pinned Notes</div>
                    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
                        ${pinned.map(n => this.renderNoteCardHtml(req.id, n)).join('')}
                    </div>
                `;
            }
            if (recent.length > 0) {
                html += `
                    <div class="task-group-title" style="${pinned.length > 0 ? '' : 'margin-top:0;'}">Recent Notes</div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        ${recent.map(n => this.renderNoteCardHtml(req.id, n)).join('')}
                    </div>
                `;
            }
            return html;
        },

        renderNoteCardHtml: function (reqId, n) {
            const contentText = n.text || n.content || '';
            return `
                <div class="note-card ${n.pinned ? 'pinned' : ''}">
                    <div class="note-card-header">
                        <span class="note-author">${escapeHtml(n.author)}</span>
                        <span class="note-date">${escapeHtml(n.date)}</span>
                    </div>
                    <div class="note-content">${escapeHtml(contentText)}</div>
                    <div class="note-actions">
                        <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;color:${n.pinned ? '#2563EB' : '#94A3B8'};" title="${n.pinned ? 'Unpin' : 'Pin'}" onclick="window.estimateRequestsApp.toggleNotePin('${escapeHtml(reqId)}', '${n.id}')">
                            📌 ${n.pinned ? 'Pinned' : 'Pin'}
                        </button>
                        <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;color:#64748B;" onclick="window.estimateRequestsApp.openEditNoteModal('${escapeHtml(reqId)}', '${n.id}')">
                            Edit
                        </button>
                        <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;color:#DC2626;" onclick="window.estimateRequestsApp.deleteNoteModal('${escapeHtml(reqId)}', '${n.id}')">
                            Delete
                        </button>
                    </div>
                </div>
            `;
        },

        onNoteSearchInput: function (val, reqId) {
            estNoteSearchQuery = val || '';
            if (!activeRecord) return;
            const container = document.getElementById('estNotesListContainer');
            if (!container) return;

            const query = estNoteSearchQuery.toLowerCase().trim();
            const notes = activeRecord.notes || [];
            const filtered = notes.filter(n => {
                if (!query) return true;
                const matchContent = (n.text || n.content || '').toLowerCase().includes(query);
                const matchAuthor = (n.author || '').toLowerCase().includes(query);
                return matchContent || matchAuthor;
            });
            const pinned = filtered.filter(n => n.pinned);
            const recent = filtered.filter(n => !n.pinned);
            container.innerHTML = this.renderNotesListHtml(activeRecord, pinned, recent, filtered.length, notes.length);
        },

        // Modal Handler Root
        openModal: function (htmlContent) {
            const overlay = document.getElementById('estActionModal');
            const card = document.getElementById('estActionModalCard');
            if (overlay && card) {
                card.innerHTML = htmlContent;
                overlay.style.display = 'flex';
                overlay.classList.add('show');
            }
        },

        closeModal: function () {
            const overlay = document.getElementById('estActionModal');
            if (overlay) {
                overlay.classList.remove('show');
                overlay.style.display = 'none';
            }
        },

        // Create Request Modal
        openCreateModal: function () {
            const users = referenceOptions.users || [];
            const companies = referenceOptions.companies || [];
            const contacts = referenceOptions.contacts || [];

            const html = `
                <div class="est-req-modal-header">
                    <h3 class="est-req-modal-title">Create Estimate Request</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.estimateRequestsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.estimateRequestsApp.submitCreateRequest(event)">
                    <div class="est-req-modal-body">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Customer Company *</label>
                                <input type="text" class="input-control" id="newReqCompany" list="newReqCompanyList" placeholder="e.g. Acme Corp" required>
                                <datalist id="newReqCompanyList">
                                    ${companies.map(c => `<option value="${escapeHtml(c.name)}">`).join('')}
                                </datalist>
                            </div>
                            <div>
                                <label class="form-label">Contact Person *</label>
                                <input type="text" class="input-control" id="newReqContact" list="newReqContactList" placeholder="e.g. Marcus Thompson" required>
                                <datalist id="newReqContactList">
                                    ${contacts.map(c => `<option value="${escapeHtml(c.name)}">`).join('')}
                                </datalist>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Email *</label>
                                <input type="email" class="input-control" id="newReqEmail" placeholder="marcus@acmecorp.com" required>
                            </div>
                            <div>
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="input-control" id="newReqPhone" placeholder="+1 (555) 000-0000">
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Request Subject *</label>
                            <input type="text" class="input-control" id="newReqSubject" placeholder="e.g. 100-Seat Sales Cloud Suite & Custom API" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Requested Services *</label>
                                <input type="text" class="input-control" id="newReqServices" placeholder="e.g. CRM Customization & SSO" required>
                            </div>
                            <div>
                                <label class="form-label">Budget Range</label>
                                <input type="text" class="input-control" id="newReqBudget" placeholder="$25,000 - $40,000">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Expected Timeline</label>
                                <select class="input-control" id="newReqTimeline">
                                    <option>Immediate (Within 30 Days)</option>
                                    <option>30 - 60 Days</option>
                                    <option>Q4 2026</option>
                                    <option>Flexible Exploration</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Assigned Owner</label>
                                <select class="input-control" id="newReqOwner">
                                    ${users.length > 0 ? users.map(u => `
                                        <option value="${u.id}">${escapeHtml(u.display_name || u.name)}</option>
                                    `).join('') : '<option value="">Unassigned</option>'}
                                </select>
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Description &amp; Requirements</label>
                            <textarea class="input-control" id="newReqDesc" rows="3" style="height:65px;resize:none;" placeholder="Technical scope details..."></textarea>
                        </div>
                    </div>
                    <div class="est-req-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Create Request</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitCreateRequest: function (e) {
            e.preventDefault();
            const company = document.getElementById('newReqCompany').value.trim();
            const contact = document.getElementById('newReqContact').value.trim();
            const email = document.getElementById('newReqEmail').value.trim();
            const phone = document.getElementById('newReqPhone').value.trim();
            const subject = document.getElementById('newReqSubject').value.trim();
            const services = document.getElementById('newReqServices').value.trim();
            const budget = document.getElementById('newReqBudget').value.trim() || '$0';
            const timeline = document.getElementById('newReqTimeline').value;
            const ownerId = document.getElementById('newReqOwner').value;
            const desc = document.getElementById('newReqDesc').value.trim();

            if (!company || !contact || !subject || !services) {
                this.showToast('Please fill all required fields.', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'create');
            formData.append('company', company);
            formData.append('contact', contact);
            formData.append('email', email);
            formData.append('phone', phone);
            formData.append('subject', subject);
            formData.append('services', services);
            formData.append('budget', budget);
            formData.append('timeline', timeline);
            if (ownerId) formData.append('owner_id', ownerId);
            formData.append('description', desc);

            fetch(API_BASE, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.closeModal();
                        this.showToast(json.message || `Request created successfully.`, 'success');
                        this.fetchSummary();
                        this.fetchRequests();
                    } else {
                        this.showToast(json.message || 'Failed to create request.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Create error:', err);
                    this.showToast('Network error while creating request.', 'error');
                });
        },

        // Edit Request Modal
        openEditModal: function (reqId) {
            const req = (activeRecord && activeRecord.id === reqId) ? activeRecord : requests.find(r => r.id === reqId);
            if (!req) return;

            const users = referenceOptions.users || [];
            const companies = referenceOptions.companies || [];
            const contacts = referenceOptions.contacts || [];

            const standardTimelines = [
                'Immediate (Within 30 Days)',
                '30 - 60 Days',
                'Q4 2026',
                'Flexible Exploration'
            ];
            const currentTimeline = req.timeline || 'Flexible Exploration';
            const timelineList = standardTimelines.includes(currentTimeline)
                ? standardTimelines
                : [currentTimeline, ...standardTimelines];

            const html = `
                <div class="est-req-modal-header">
                    <h3 class="est-req-modal-title">Edit Request: ${escapeHtml(req.id)}</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.estimateRequestsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.estimateRequestsApp.submitEditRequest(event, '${escapeHtml(req.id)}')">
                    <div class="est-req-modal-body">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Customer Company *</label>
                                <input type="text" class="input-control" id="editReqCompany" list="editReqCompanyList" value="${escapeHtml(req.company || '')}" placeholder="e.g. Acme Corp" required>
                                <datalist id="editReqCompanyList">
                                    ${companies.map(c => `<option value="${escapeHtml(c.name)}">`).join('')}
                                </datalist>
                            </div>
                            <div>
                                <label class="form-label">Contact Person *</label>
                                <input type="text" class="input-control" id="editReqContact" list="editReqContactList" value="${escapeHtml(req.contact || '')}" placeholder="e.g. Marcus Thompson" required>
                                <datalist id="editReqContactList">
                                    ${contacts.map(c => `<option value="${escapeHtml(c.name)}">`).join('')}
                                </datalist>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Email *</label>
                                <input type="email" class="input-control" id="editReqEmail" value="${escapeHtml(req.email || '')}" placeholder="marcus@acmecorp.com" required>
                            </div>
                            <div>
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="input-control" id="editReqPhone" value="${escapeHtml(req.phone || '')}" placeholder="+1 (555) 000-0000">
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Request Subject *</label>
                            <input type="text" class="input-control" id="editReqSubject" value="${escapeHtml(req.subject || '')}" placeholder="e.g. 100-Seat Sales Cloud Suite & Custom API" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Requested Services *</label>
                                <input type="text" class="input-control" id="editReqServices" value="${escapeHtml(req.services || '')}" placeholder="e.g. CRM Customization & SSO" required>
                            </div>
                            <div>
                                <label class="form-label">Budget Range</label>
                                <input type="text" class="input-control" id="editReqBudget" value="${escapeHtml(req.raw_budget !== undefined ? req.raw_budget : (req.budget || ''))}" placeholder="${escapeHtml(referenceOptions.currency_symbol || '$')}25,000 - ${escapeHtml(referenceOptions.currency_symbol || '$')}40,000">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Expected Timeline</label>
                                <select class="input-control" id="editReqTimeline">
                                    ${timelineList.map(t => `
                                        <option value="${escapeHtml(t)}" ${currentTimeline === t ? 'selected' : ''}>${escapeHtml(t)}</option>
                                    `).join('')}
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Status</label>
                                <select class="input-control" id="editReqStatus">
                                    <option value="New" ${req.status==='New'?'selected':''}>New</option>
                                    <option value="In Review" ${req.status==='In Review'?'selected':''}>In Review</option>
                                    <option value="More Info Needed" ${req.status==='More Info Needed'?'selected':''}>More Info Needed</option>
                                    <option value="Approved" ${req.status==='Approved'?'selected':''}>Approved</option>
                                    <option value="Rejected" ${req.status==='Rejected'?'selected':''}>Rejected</option>
                                    <option value="Converted" ${req.status==='Converted'?'selected':''}>Converted</option>
                                </select>
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Assigned Owner</label>
                            <select class="input-control" id="editReqOwner">
                                <option value="">Unassigned</option>
                                ${users.map(u => `
                                    <option value="${u.id}" ${(req.owner_id == u.id || req.owner === (u.display_name || u.name)) ? 'selected' : ''}>${escapeHtml(u.display_name || u.name)}</option>
                                `).join('')}
                            </select>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Description &amp; Requirements</label>
                            <textarea class="input-control" id="editReqDesc" rows="3" style="height:65px;resize:none;" placeholder="Technical scope details...">${escapeHtml(req.description || '')}</textarea>
                        </div>
                    </div>
                    <div class="est-req-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitEditRequest: function (e, reqId) {
            e.preventDefault();
            const company = document.getElementById('editReqCompany').value.trim();
            const contact = document.getElementById('editReqContact').value.trim();
            const email = document.getElementById('editReqEmail').value.trim();
            const phone = document.getElementById('editReqPhone').value.trim();
            const subject = document.getElementById('editReqSubject').value.trim();
            const services = document.getElementById('editReqServices').value.trim();
            const budget = document.getElementById('editReqBudget').value.trim();
            const timeline = document.getElementById('editReqTimeline').value;
            const status = document.getElementById('editReqStatus').value;
            const ownerId = document.getElementById('editReqOwner').value;
            const desc = document.getElementById('editReqDesc').value.trim();

            if (!company || !contact || !subject || !services) {
                this.showToast('Please fill all required fields.', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('id', reqId);
            formData.append('company', company);
            formData.append('contact', contact);
            formData.append('email', email);
            formData.append('phone', phone);
            formData.append('subject', subject);
            formData.append('services', services);
            formData.append('budget', budget);
            formData.append('timeline', timeline);
            formData.append('status', status);
            formData.append('owner_id', ownerId);
            formData.append('description', desc);

            fetch(API_BASE, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.closeModal();
                        this.showToast('Estimate request updated successfully.', 'success');
                        this.fetchSummary();
                        this.fetchRequests();
                        if (activeRequestId === reqId) this.openDrawer(reqId);
                    } else {
                        this.showToast(json.message || 'Failed to update request.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Update error:', err);
                    this.showToast('Network error while updating.', 'error');
                });
        },

        // Change Status Modal
        openChangeStatusModal: function (reqId) {
            const req = (activeRecord && activeRecord.id === reqId) ? activeRecord : requests.find(r => r.id === reqId);
            if (!req) return;

            const html = `
                <div class="est-req-modal-header">
                    <h3 class="est-req-modal-title">Update Request Status</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.estimateRequestsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="est-req-modal-body">
                    <div style="margin-bottom:14px;">
                        <label class="form-label">Select New Status for ${escapeHtml(req.company)}</label>
                        <select class="input-control" id="changeStatusSelect">
                            <option value="New" ${req.status==='New'?'selected':''}>New</option>
                            <option value="In Review" ${req.status==='In Review'?'selected':''}>In Review</option>
                            <option value="More Info Needed" ${req.status==='More Info Needed'?'selected':''}>More Info Needed</option>
                            <option value="Approved" ${req.status==='Approved'?'selected':''}>Approved</option>
                            <option value="Rejected" ${req.status==='Rejected'?'selected':''}>Rejected</option>
                            <option value="Converted" ${req.status==='Converted'?'selected':''}>Converted</option>
                        </select>
                    </div>
                </div>
                <div class="est-req-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.estimateRequestsApp.confirmChangeStatus('${escapeHtml(req.id)}')">Update Status</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmChangeStatus: function (reqId) {
            const newStatus = document.getElementById('changeStatusSelect').value;

            const formData = new FormData();
            formData.append('action', 'change_status');
            formData.append('id', reqId);
            formData.append('status', newStatus);

            fetch(API_BASE, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.closeModal();
                        this.showToast(`Status updated to "${newStatus}".`, 'success');
                        this.fetchSummary();
                        this.fetchRequests();
                        if (activeRequestId === reqId) this.openDrawer(reqId);
                    } else {
                        this.showToast(json.message || 'Failed to change status.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Status change error:', err);
                    this.showToast('Network error while changing status.', 'error');
                });
        },

        // Assign Owner Modal
        openAssignOwnerModal: function (reqId) {
            const req = (activeRecord && activeRecord.id === reqId) ? activeRecord : requests.find(r => r.id === reqId);
            if (!req) return;

            const users = referenceOptions.users || [];

            const html = `
                <div class="est-req-modal-header">
                    <h3 class="est-req-modal-title">Assign Lead Owner</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.estimateRequestsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="est-req-modal-body">
                    <div style="margin-bottom:14px;">
                        <label class="form-label">Assign to NexFlow Team Member</label>
                        <select class="input-control" id="assignOwnerSelect">
                            ${users.map(u => `
                                <option value="${u.id}" ${req.owner_id == u.id || req.owner === (u.display_name || u.name) ? 'selected' : ''}>${escapeHtml(u.display_name || u.name)}</option>
                            `).join('')}
                        </select>
                    </div>
                </div>
                <div class="est-req-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.estimateRequestsApp.confirmAssignOwner('${escapeHtml(req.id)}')">Reassign</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmAssignOwner: function (reqId) {
            const select = document.getElementById('assignOwnerSelect');
            const ownerId = select.value;
            const ownerName = select.options[select.selectedIndex]?.text || '';

            const formData = new FormData();
            formData.append('action', 'assign_owner');
            formData.append('id', reqId);
            formData.append('owner_id', ownerId);

            fetch(API_BASE, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.closeModal();
                        this.showToast(`Request reassigned to ${ownerName}.`, 'success');
                        this.fetchSummary();
                        this.fetchRequests();
                        if (activeRequestId === reqId) this.openDrawer(reqId);
                    } else {
                        this.showToast(json.message || 'Failed to reassign owner.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Assign owner error:', err);
                    this.showToast('Network error while reassigning owner.', 'error');
                });
        },

        // Create Estimate Modal (Workflow: Approves and issues estimate)
        openCreateEstimateModal: function (reqId) {
            const req = (activeRecord && activeRecord.id === reqId) ? activeRecord : requests.find(r => r.id === reqId);
            if (!req) return;

            const html = `
                <div class="est-req-modal-header">
                    <h3 class="est-req-modal-title">Generate Estimate: ${escapeHtml(req.company)}</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.estimateRequestsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.estimateRequestsApp.submitCreateEstimate(event, '${escapeHtml(req.id)}')">
                    <div class="est-req-modal-body">
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Estimate Title</label>
                            <input type="text" class="input-control" id="estTitle" value="Formal Estimate — ${escapeHtml(req.subject)}" required>
                        </div>
                        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:10px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Line Item</label>
                                <input type="text" class="input-control" value="${escapeHtml(req.services)}">
                            </div>
                            <div>
                                <label class="form-label">Qty</label>
                                <input type="number" class="input-control" value="1">
                            </div>
                            <div>
                                <label class="form-label">Unit Price ($)</label>
                                <input type="number" class="input-control" id="estPrice" value="35000" oninput="window.estimateRequestsApp.calcEstTotal()">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Discount (%)</label>
                                <input type="number" class="input-control" id="estDiscount" value="10" oninput="window.estimateRequestsApp.calcEstTotal()">
                            </div>
                            <div>
                                <label class="form-label">Valid Until Date</label>
                                <input type="date" class="input-control" value="2026-09-30">
                            </div>
                        </div>
                        <div style="background:#F8FAFC;padding:12px 16px;border:1px solid var(--border-divider);border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-weight:600;color:var(--text-heading);">Calculated Total:</span>
                            <span style="font-size:18px;font-weight:700;color:var(--primary);" id="estTotalDisplay">$31,500.00</span>
                        </div>
                    </div>
                    <div class="est-req-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Issue Estimate</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        calcEstTotal: function () {
            const price = parseFloat(document.getElementById('estPrice')?.value) || 0;
            const discount = parseFloat(document.getElementById('estDiscount')?.value) || 0;
            const total = price * (1 - discount / 100);
            const el = document.getElementById('estTotalDisplay');
            if (el) el.textContent = '$' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        submitCreateEstimate: function (e, reqId) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('action', 'create_estimate');
            formData.append('id', reqId);

            fetch(API_BASE, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.closeModal();
                        this.showToast('Estimate issued successfully.', 'success');
                        this.fetchSummary();
                        this.fetchRequests();
                        if (activeRequestId === reqId) this.openDrawer(reqId);
                    } else {
                        this.showToast(json.message || 'Failed to issue estimate.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Issue estimate error:', err);
                    this.showToast('Network error while issuing estimate.', 'error');
                });
        },

        // Convert to Deal Modal
        openConvertToDealModal: function (reqId) {
            const req = (activeRecord && activeRecord.id === reqId) ? activeRecord : requests.find(r => r.id === reqId);
            if (!req) return;

            if (req.status === 'Converted' || req.deal_id) {
                const dealLabel = req.deal_code ? `Deal <strong>${escapeHtml(req.deal_code)}</strong>` : 'a Sales Opportunity';
                const html = `
                    <div class="est-req-modal-header">
                        <h3 class="est-req-modal-title">Already Converted</h3>
                        <button type="button" class="btn btn-ghost btn-xs" onclick="window.estimateRequestsApp.closeModal()">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="est-req-modal-body">
                        <p style="font-size:13.5px;color:var(--text-body);line-height:1.5;">
                            This estimate request has already been converted to ${dealLabel}.
                        </p>
                        <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;padding:12px;font-size:13px;color:#166534;">
                            You can view and manage this opportunity directly in the Sales Pipeline.
                        </div>
                    </div>
                    <div class="est-req-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeModal()">Close</button>
                        <a href="pipeline.php" class="btn btn-primary btn-sm" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                            Go to Sales Pipeline &rarr;
                        </a>
                    </div>
                `;
                this.openModal(html);
                return;
            }

            const html = `
                <div class="est-req-modal-header">
                    <h3 class="est-req-modal-title">Convert Request to Deal</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.estimateRequestsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="est-req-modal-body">
                    <p style="font-size:13.5px;color:var(--text-body);line-height:1.5;">
                        Convert pricing request <strong>"${escapeHtml(req.subject)}"</strong> for <strong>${escapeHtml(req.company)}</strong> into an active Sales Pipeline Opportunity?
                    </p>
                    <div style="background:#F8FAFC;border:1px solid var(--border-divider);border-radius:8px;padding:12px;font-size:13px;color:var(--text-secondary);">
                        Will set request status to <strong>Converted</strong> and create a pipeline record in the <strong>Prospect</strong> stage.
                    </div>
                </div>
                <div class="est-req-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.estimateRequestsApp.confirmConvertToDeal('${escapeHtml(req.id)}')">Convert to Deal</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmConvertToDeal: function (reqId) {
            const formData = new FormData();
            formData.append('action', 'convert_to_deal');
            formData.append('id', reqId);

            fetch(API_BASE, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.closeModal();
                        const dealCode = json.data && json.data.deal_code ? ` (${json.data.deal_code})` : '';
                        this.showToast(json.message || `Request converted to Sales Opportunity${dealCode}.`, 'success');
                        this.fetchSummary();
                        this.fetchRequests();
                        if (activeRequestId === reqId) this.openDrawer(reqId);
                    } else {
                        this.showToast(json.message || 'Failed to convert to deal.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Convert deal error:', err);
                    this.showToast('Network error while converting to deal.', 'error');
                });
        },

        duplicateRequest: function (reqId) {
            const formData = new FormData();
            formData.append('action', 'duplicate');
            formData.append('id', reqId);

            fetch(API_BASE, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.showToast(json.message || 'Duplicated request.', 'info');
                        this.fetchSummary();
                        this.fetchRequests();
                    } else {
                        this.showToast(json.message || 'Failed to duplicate request.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Duplicate error:', err);
                    this.showToast('Network error while duplicating.', 'error');
                });
        },

        openDeleteModal: function (reqId) {
            const req = (activeRecord && activeRecord.id === reqId) ? activeRecord : requests.find(r => r.id === reqId);
            if (!req) return;

            const html = `
                <div class="est-req-modal-header">
                    <h3 class="est-req-modal-title">Delete Estimate Request</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.estimateRequestsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="est-req-modal-body">
                    <p style="font-size:13.5px;color:var(--text-body);line-height:1.5;">
                        Are you sure you want to delete request <strong>"${escapeHtml(req.subject)}"</strong> (${escapeHtml(req.id)})?
                    </p>
                </div>
                <div class="est-req-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" style="background:#DC2626;border-color:#DC2626;" onclick="window.estimateRequestsApp.confirmDelete('${escapeHtml(req.id)}')">Delete Request</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmDelete: function (reqId) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', reqId);

            fetch(API_BASE, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.closeModal();
                        if (activeRequestId === reqId) this.closeDrawer();
                        this.showToast('Estimate request removed.', 'info');
                        this.fetchSummary();
                        this.fetchRequests();
                    } else {
                        this.showToast(json.message || 'Failed to delete request.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Delete error:', err);
                    this.showToast('Network error while deleting.', 'error');
                });
        },

        // Notes Management
        openAddNoteModal: function (reqId) {
            const html = `
                <div class="est-req-modal-header">
                    <h3 class="est-req-modal-title">Add Note to ${escapeHtml(reqId)}</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.estimateRequestsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.estimateRequestsApp.submitNoteForm(event, '${escapeHtml(reqId)}', null)">
                    <div class="est-req-modal-body">
                        <div class="form-group" style="margin-bottom:14px;">
                            <label class="form-label">Note Content *</label>
                            <textarea class="input-control" id="noteFormContent" rows="4" style="height:100px;resize:vertical;" placeholder="Add internal note regarding customer requirements, pricing request, or budget..." required></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;color:#334155;">
                                <input type="checkbox" id="noteFormPinned" class="input-checkbox">
                                <span>📌 Pin this note to top</span>
                            </label>
                        </div>
                    </div>
                    <div class="est-req-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Note</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        openEditNoteModal: function (reqId, noteId) {
            const note = activeRecord && activeRecord.notes ? activeRecord.notes.find(n => n.id == noteId) : null;
            if (!note) return;

            const html = `
                <div class="est-req-modal-header">
                    <h3 class="est-req-modal-title">Edit Note</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.estimateRequestsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.estimateRequestsApp.submitNoteForm(event, '${escapeHtml(reqId)}', ${note.id})">
                    <div class="est-req-modal-body">
                        <div class="form-group" style="margin-bottom:14px;">
                            <label class="form-label">Note Content *</label>
                            <textarea class="input-control" id="noteFormContent" rows="4" style="height:100px;resize:vertical;" required>${escapeHtml(note.text || note.content || '')}</textarea>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;color:#334155;">
                                <input type="checkbox" id="noteFormPinned" class="input-checkbox" ${note.pinned ? 'checked' : ''}>
                                <span>📌 Pin this note to top</span>
                            </label>
                        </div>
                    </div>
                    <div class="est-req-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Update Note</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitNoteForm: function (e, reqId, noteId) {
            e.preventDefault();
            const content = document.getElementById('noteFormContent').value.trim();
            const isPinned = document.getElementById('noteFormPinned').checked ? 1 : 0;
            if (!content) return;

            const formData = new FormData();
            if (noteId) {
                formData.append('action', 'notes_update');
                formData.append('note_id', noteId);
                formData.append('content', content);
                formData.append('pinned', isPinned);
            } else {
                formData.append('action', 'notes_create');
                formData.append('request_id', reqId);
                formData.append('content', content);
                formData.append('pinned', isPinned);
            }

            fetch(API_BASE, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.closeModal();
                        this.showToast(noteId ? 'Note updated successfully.' : 'Note added to request.', 'success');
                        if (activeRequestId === reqId) this.openDrawer(reqId);
                    } else {
                        this.showToast(json.message || 'Failed to save note.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Note submit error:', err);
                    this.showToast('Network error while saving note.', 'error');
                });
        },

        toggleNotePin: function (reqId, noteId) {
            const formData = new FormData();
            formData.append('action', 'notes_toggle_pin');
            formData.append('note_id', noteId);

            fetch(API_BASE, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.showToast(json.message || 'Pin status toggled.', 'info');
                        if (activeRequestId === reqId) this.openDrawer(reqId);
                    } else {
                        this.showToast(json.message || 'Failed to toggle pin.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Pin toggle error:', err);
                    this.showToast('Network error while toggling pin.', 'error');
                });
        },

        deleteNoteModal: function (reqId, noteId) {
            const html = `
                <div class="est-req-modal-header">
                    <h3 class="est-req-modal-title">Delete Note</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.estimateRequestsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="est-req-modal-body">
                    <p style="font-size:13.5px;color:#334155;margin:0;">Are you sure you want to delete this note? This action cannot be undone.</p>
                </div>
                <div class="est-req-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" style="background:#DC2626;color:#FFF;border:none;" onclick="window.estimateRequestsApp.confirmDeleteNote('${escapeHtml(reqId)}', ${noteId})">Delete</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmDeleteNote: function (reqId, noteId) {
            const formData = new FormData();
            formData.append('action', 'notes_delete');
            formData.append('note_id', noteId);

            fetch(API_BASE, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.closeModal();
                        this.showToast('Note deleted.', 'info');
                        if (activeRequestId === reqId) this.openDrawer(reqId);
                    } else {
                        this.showToast(json.message || 'Failed to delete note.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Delete note error:', err);
                    this.showToast('Network error while deleting note.', 'error');
                });
        },

        // Log Activity
        logActivityPrompt: function (reqId) {
            const html = `
                <div class="est-req-modal-header">
                    <h3 class="est-req-modal-title">Log Review Activity</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.estimateRequestsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.estimateRequestsApp.submitLogActivity(event, '${escapeHtml(reqId)}')">
                    <div class="est-req-modal-body">
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Activity Summary</label>
                            <input type="text" class="input-control" id="logSummary" placeholder="e.g. Scoping call with solutions architect" required>
                        </div>
                    </div>
                    <div class="est-req-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.estimateRequestsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Activity</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitLogActivity: function (e, reqId) {
            e.preventDefault();
            const text = document.getElementById('logSummary').value.trim();
            if (!text) return;

            const formData = new FormData();
            formData.append('action', 'log_activity');
            formData.append('id', reqId);
            formData.append('text', text);

            fetch(API_BASE, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(json => {
                    if (json.success) {
                        this.closeModal();
                        this.showToast('Activity logged successfully.', 'success');
                        if (activeRequestId === reqId) this.openDrawer(reqId);
                    } else {
                        this.showToast(json.message || 'Failed to log activity.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Log activity error:', err);
                    this.showToast('Network error while logging activity.', 'error');
                });
        },

        // Export CSV
        exportCSV: function () {
            const params = new URLSearchParams({
                action: 'export_csv',
                search: currentSearch,
                status: currentFilterTab,
                service: currentServiceFilter,
                sort: currentSort
            });
            window.location.href = `${API_BASE}?${params.toString()}`;
            this.showToast('Downloading estimate requests CSV...', 'info');
        },

        // Filter Popover
        toggleFilterPopover: function (e) {
            if (e && e.stopPropagation) e.stopPropagation();
            const popover = document.getElementById('estimateRequestsFilterPopover');
            if (popover) popover.classList.toggle('show');
        },

        closeFilterPopover: function () {
            const popover = document.getElementById('estimateRequestsFilterPopover');
            if (popover) popover.classList.remove('show');
        },

        applyFilters: function () {
            this.closeFilterPopover();
            const serviceVal = document.getElementById('filterEstService')?.value || 'All';
            const statusVal = document.getElementById('filterEstStatus')?.value || 'All';

            currentServiceFilter = serviceVal;
            currentFilterTab = statusVal;
            currentPage = 1;

            const statusSelect = document.getElementById('reqStatusSelect');
            if (statusSelect) statusSelect.value = statusVal;

            this.fetchRequests();
        },

        clearFilters: function () {
            if (document.getElementById('filterEstService')) document.getElementById('filterEstService').value = 'All';
            if (document.getElementById('filterEstStatus')) document.getElementById('filterEstStatus').value = 'All';
            const statusSelect = document.getElementById('reqStatusSelect');
            if (statusSelect) statusSelect.value = 'All';
            const searchInput = document.getElementById('reqSearchInput');
            if (searchInput) searchInput.value = '';

            this.closeFilterPopover();
            currentSearch = '';
            currentServiceFilter = 'All';
            currentFilterTab = 'All';
            currentPage = 1;
            this.fetchRequests();
        },

        showToast: function (msg, type = 'info') {
            const container = document.getElementById('estimateRequestsToastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `est-req-toast ${type}`;
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
        document.addEventListener('DOMContentLoaded', () => window.estimateRequestsApp.init());
    } else {
        window.estimateRequestsApp.init();
    }
})();
