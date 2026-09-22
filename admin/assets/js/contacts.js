/**
 * NexFlow CRM — Contacts Management JavaScript Controller
 * Connects existing Leads-grade UI/UX to the real MySQL backend via admin/api/contacts.php.
 * Strictly ZERO mock data, ZERO client storage for business data.
 */

(function () {
    'use strict';

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNoteDate(dateStr) {
        if (!dateStr) return 'Just now';
        const str = String(dateStr).trim();
        if (/^[A-Za-z]{3}\s+\d{1,2},\s+\d{4}\s+•\s+\d{1,2}:\d{2}\s+(AM|PM)$/i.test(str)) {
            return str;
        }
        let d = new Date(str.replace(' • ', ' '));
        if (isNaN(d.getTime())) {
            const m = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (m) {
                d = new Date(parseInt(m[1]), parseInt(m[2]) - 1, parseInt(m[3]), 12, 0);
            } else {
                return str;
            }
        }
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const month = months[d.getMonth()];
        const day = String(d.getDate()).padStart(2, '0');
        const year = d.getFullYear();
        let hours = d.getHours();
        const minutes = String(d.getMinutes()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        return `${month} ${day}, ${year} • ${hours}:${minutes} ${ampm}`;
    }

    // State Variables
    let contacts = [];
    let activeContactId = null;
    let activeContactData = null;
    let availableOwners = [];
    let availableCompanies = [];
    let currentCardFilter = 'All';
    let currentRelFilter = 'All';
    let currentOwnerFilter = 'All';
    let currentSort = 'name-asc';
    let searchQuery = '';
    let currentPage = 1;
    let pageSize = 8;
    let totalPages = 1;
    let totalRecords = 0;
    let selectedIds = new Set();
    let notesSearchQuery = '';
    let searchDebounceTimer = null;

    // Helper: Pagination range generator
    function getPaginationArray(current, total) {
        if (total <= 7) {
            return Array.from({ length: total }, (_, i) => i + 1);
        }
        if (current <= 4) {
            return [1, 2, 3, 4, 5, '...', total];
        }
        if (current >= total - 3) {
            return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
        }
        return [1, '...', current - 1, current, current + 1, '...', total];
    }

    // API Helper: Standard fetch wrapper
    async function apiRequest(endpoint, options = {}) {
        try {
            const res = await fetch(endpoint, options);
            const data = await res.json();
            return data;
        } catch (e) {
            console.error('API Error:', e);
            return { success: false, message: 'Network or server error.' };
        }
    }

    // Fetch contacts from backend API
    async function fetchContacts(callback) {
        const params = new URLSearchParams({
            action: 'list',
            search: searchQuery,
            relationship: currentRelFilter,
            owner: currentOwnerFilter,
            card_filter: currentCardFilter,
            sort: currentSort,
            page: currentPage,
            page_size: pageSize
        });

        const res = await apiRequest(`api/contacts.php?${params.toString()}`);
        if (res.success && res.data) {
            contacts = res.data.contacts || [];
            availableOwners = res.data.owners || [];
            availableCompanies = res.data.companies || [];
            const pag = res.data.pagination || {};
            totalRecords = pag.total || 0;
            totalPages = pag.totalPages || 1;
            currentPage = pag.page || 1;

            // Update KPI Counters
            if (res.data.kpis) {
                const k = res.data.kpis;
                const badge = document.getElementById('contactsTotalCountBadge');
                if (badge) badge.textContent = `${k.total} contacts`;
                const elTotal = document.getElementById('kpiTotalContacts');
                const elActive = document.getElementById('kpiActiveContacts');
                const elRecent = document.getElementById('kpiRecentContacts');
                const elUnassigned = document.getElementById('kpiUnassignedContacts');
                const elInactive = document.getElementById('kpiInactiveContacts');

                if (elTotal) elTotal.textContent = k.total;
                if (elActive) elActive.textContent = k.active;
                if (elRecent) elRecent.textContent = k.recent;
                if (elUnassigned) elUnassigned.textContent = k.unassigned;
                if (elInactive) elInactive.textContent = k.inactive;
            }

            // Populate filter owner dropdown if needed
            const filterOwnerSel = document.getElementById('filterOwner');
            if (filterOwnerSel && filterOwnerSel.options.length <= 1 && availableOwners.length > 0) {
                availableOwners.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.name;
                    opt.textContent = u.name;
                    filterOwnerSel.appendChild(opt);
                });
            }

            window.contactsApp.renderTable();
            if (typeof callback === 'function') callback();
        } else {
            console.error('Failed to load contacts:', res.message);
            window.contactsApp.renderTable();
        }
    }

    // Public Controller Window Binding
    window.contactsApp = {
        init: function () {
            this.setupEventListeners();
            fetchContacts();
        },

        getActiveContactId: function () {
            return activeContactId;
        },

        setupEventListeners: function () {
            // Close filter popover on outside click
            document.addEventListener('click', (e) => {
                const pop = document.getElementById('contactsFilterPopover');
                const btn = document.getElementById('btnContactFilter');
                if (pop && btn && !pop.contains(e.target) && !btn.contains(e.target)) {
                    this.closeFilterPopover();
                }

                const menu = document.getElementById('contactsRowDropdown');
                if (menu && !menu.contains(e.target)) {
                    menu.classList.remove('show');
                }

                const drawerMenu = document.getElementById('contactsDrawerMenu');
                const drawerBtn = document.getElementById('drawerHeaderActionsBtn');
                if (drawerMenu && drawerBtn && !drawerMenu.contains(e.target) && !drawerBtn.contains(e.target)) {
                    drawerMenu.classList.remove('show');
                }
            });

            // Close on escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeModal();
                    this.closeFilterPopover();
                    this.closeDrawer();
                }
            });
        },

        filterByCard: function (cardType) {
            currentCardFilter = cardType;
            currentPage = 1;
            fetchContacts();
        },

        filterByTab: function (tabName, btn) {
            currentCardFilter = tabName;
            currentPage = 1;
            fetchContacts();
        },

        handleSearch: function (query) {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                searchQuery = query.trim();
                currentPage = 1;
                fetchContacts();
            }, 300);
        },

        handleSort: function (sortVal) {
            currentSort = sortVal;
            currentPage = 1;
            fetchContacts();
        },

        toggleFilterPopover: function (e) {
            if (e) e.stopPropagation();
            const pop = document.getElementById('contactsFilterPopover');
            if (pop) pop.classList.toggle('show');
        },

        closeFilterPopover: function () {
            const pop = document.getElementById('contactsFilterPopover');
            if (pop) pop.classList.remove('show');
        },

        applyFilters: function () {
            const relSel = document.getElementById('filterRelationship');
            const ownerSel = document.getElementById('filterOwner');
            currentRelFilter = relSel ? relSel.value : 'All';
            currentOwnerFilter = ownerSel ? ownerSel.value : 'All';
            currentPage = 1;
            this.closeFilterPopover();
            fetchContacts();
        },

        clearFilters: function () {
            const relSel = document.getElementById('filterRelationship');
            const ownerSel = document.getElementById('filterOwner');
            if (relSel) relSel.value = 'All';
            if (ownerSel) ownerSel.value = 'All';
            currentRelFilter = 'All';
            currentOwnerFilter = 'All';
            currentPage = 1;
            this.closeFilterPopover();
            fetchContacts();
        },

        renderTable: function () {
            const tbody = document.getElementById('contactsTbody');
            if (!tbody) return;

            // Update range text
            const rangeSpan = document.getElementById('pagingRange');
            const totalSpan = document.getElementById('pagingTotal');
            if (rangeSpan && totalSpan) {
                totalSpan.textContent = totalRecords;
                if (totalRecords === 0) {
                    rangeSpan.textContent = '0–0';
                } else {
                    const start = (currentPage - 1) * pageSize + 1;
                    const end = Math.min(currentPage * pageSize, totalRecords);
                    rangeSpan.textContent = `${start}–${end}`;
                }
            }

            // Pagination Controls
            const controls = document.getElementById('paginationControls');
            if (controls) {
                if (totalPages <= 1) {
                    controls.innerHTML = '';
                } else {
                    const arr = getPaginationArray(currentPage, totalPages);
                    let html = `
                        <button type="button" class="pagination-btn" id="prevPageBtn" ${currentPage === 1 ? 'disabled' : ''} onclick="window.contactsApp.prevPage()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                    `;
                    arr.forEach(p => {
                        if (p === '...') {
                            html += `<span class="pagination-ellipsis">...</span>`;
                        } else {
                            const isActive = p === currentPage;
                            html += `<button type="button" class="pagination-btn ${isActive ? 'active' : ''}" onclick="window.contactsApp.goToPage(${p})">${p}</button>`;
                        }
                    });
                    html += `
                        <button type="button" class="pagination-btn" id="nextPageBtn" ${currentPage === totalPages ? 'disabled' : ''} onclick="window.contactsApp.nextPage()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    `;
                    controls.innerHTML = html;
                }
            }

            if (contacts.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" style="text-align:center;padding:48px 16px;color:var(--text-secondary);">
                            No contacts found.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = contacts.map(c => {
                const relBadge = c.relationship === 'Customer' ? 'badge-customer' :
                                 c.relationship === 'Prospect' ? 'badge-prospect' :
                                 c.relationship === 'Partner' ? 'badge-partner' : 'badge-inactive';

                const initials = c.firstName ? (c.firstName[0] + (c.lastName ? c.lastName[0] : '')) : (c.name ? c.name[0] : 'C');

                return `
                    <tr data-id="${c.id}">
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="avatar avatar-sm" style="background-color:${c.ownerColor || '#7C3AED'};">
                                    ${initials}
                                </div>
                                <div>
                                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                        <span class="contact-name-clickable" style="font-weight:600;color:var(--text-heading);cursor:pointer;" onclick="window.contactsApp.openDrawer('${c.id}')">
                                            ${escapeHtml(c.name)}
                                        </span>
                                        ${c.isPrimary ? `<span class="badge-primary-contact" title="${escapeHtml(c.primaryCompanyName ? 'Primary Contact for ' + c.primaryCompanyName : 'Primary Contact')}"><span class="badge-primary-star">★</span> Primary</span>` : ''}
                                    </div>
                                    <div style="font-size:12px;color:var(--text-muted);">${escapeHtml(c.jobTitle || '—')}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            ${(() => {
                                if (c.companies && c.companies.length > 1) {
                                    const allNames = c.companies.map(comp => comp.name).join(', ');
                                    return `<div style="font-weight:600;color:var(--text-heading);display:flex;align-items:center;gap:6px;" title="${escapeHtml(allNames)}">
                                        <span>${escapeHtml(c.companies[0].name)}</span>
                                        <span style="font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px;background:var(--bg-subtle, #f3f4f6);color:var(--text-muted);white-space:nowrap;">+${c.companies.length - 1} more</span>
                                    </div>`;
                                }
                                return `<div style="font-weight:600;color:var(--text-heading);">${escapeHtml(c.company || '—')}</div>`;
                            })()}
                            ${c.companyLocation ? `<div style="font-size:11px;color:var(--text-secondary);">${escapeHtml(c.companyLocation)}</div>` : ''}
                        </td>
                        <td>
                            <span class="badge-rel ${relBadge}">● ${escapeHtml(c.relationship || 'Prospect')}</span>
                        </td>
                        <td>
                            <div style="font-weight:500;color:var(--text-heading);">${escapeHtml(c.email || '—')}</div>
                            <div style="font-size:11px;color:var(--text-secondary);">${escapeHtml(c.phone || '—')}</div>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <div class="avatar avatar-xs" style="background-color:${c.ownerColor || '#94A3B8'};font-size:9px;">${c.ownerInitials || 'UN'}</div>
                                <span style="font-size:12px;color:var(--text-secondary);">${escapeHtml(c.owner || 'Unassigned')}</span>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:700;color:var(--text-heading);">$${(c.dealsValue || 0).toLocaleString()}</div>
                            <div style="font-size:11px;color:var(--text-secondary);">${c.dealsCount || 0} ${(c.dealsCount === 1) ? 'deal' : 'deals'}</div>
                        </td>
                        <td>
                            <span style="font-size:12px;color:var(--text-body);font-weight:500;">${c.lastActivity || '—'}</span>
                            <div style="font-size:11px;color:var(--text-muted);">${c.lastActivityType || ''}</div>
                        </td>
                        <td style="text-align:center;white-space:nowrap;">
                            <div style="display:flex;align-items:center;justify-content:center;gap:4px;">
                                <button type="button" class="btn btn-ghost btn-xs" title="Call Contact" style="padding:4px;color:#12B76A;" onclick="window.contactsApp.openCallChoice('${c.id}', this)">
                                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                                </button>
                                <button type="button" class="btn btn-ghost btn-xs" title="WhatsApp" style="padding:4px;color:#25D366;" onclick="window.contactsApp.openWhatsApp('${c.id}', this)">
                                    <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                </button>
                                <button type="button" class="btn btn-ghost btn-xs" title="Send Email" style="padding:4px;color:#2563EB;" onclick="window.contactsApp.openEmail('${c.id}', this)">
                                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </button>
                                <button type="button" class="btn btn-ghost btn-xs" title="More Options" style="padding:4px;" onclick="window.contactsApp.openRowMenu(event, '${c.id}')">
                                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        },

        goToPage: function (p) {
            currentPage = p;
            fetchContacts();
        },

        prevPage: function () {
            if (currentPage > 1) {
                currentPage--;
                fetchContacts();
            }
        },

        nextPage: function () {
            if (currentPage < totalPages) {
                currentPage++;
                fetchContacts();
            }
        },

        toggleSelection: function (id, isChecked) {
            if (isChecked) selectedIds.add(id);
            else selectedIds.delete(id);
            this.updateBulkUI();
            this.renderTable();
        },

        toggleSelectAll: function (isChecked) {
            if (isChecked) contacts.forEach(c => selectedIds.add(c.id));
            else selectedIds.clear();
            this.updateBulkUI();
            this.renderTable();
        },

        updateBulkUI: function () {
            const bulk = document.getElementById('bulkActions');
            const countSpan = document.getElementById('selectedCountText');
            if (bulk && countSpan) {
                if (selectedIds.size > 0) {
                    bulk.style.display = 'flex';
                    countSpan.textContent = selectedIds.size;
                } else {
                    bulk.style.display = 'none';
                }
            }
        },

        deleteSelectedContacts: async function () {
            if (selectedIds.size === 0) return;
            if (!confirm(`Are you sure you want to delete ${selectedIds.size} contacts?`)) return;

            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_bulk',
                    ids: Array.from(selectedIds)
                })
            });

            if (res.success) {
                selectedIds.clear();
                this.updateBulkUI();
                this.showToast(res.message, 'success');
                fetchContacts();
            } else {
                this.showToast(res.message || 'Failed to delete contacts.', 'error');
            }
        },

        // Row Communication Adapters
        openCallChoice: function (cntId, btn) {
            const cnt = contacts.find(c => c.id == cntId);
            if (!cnt) return;
            if (window.leadComm && typeof window.leadComm.openCallChoice === 'function') {
                const leadAdapter = {
                    id: cnt.id,
                    name: cnt.name,
                    company: cnt.company,
                    phone: cnt.phone,
                    cleanPhone: cnt.cleanPhone,
                    assigneeColor: cnt.ownerColor
                };
                window.activeLeadForComm = leadAdapter;
                window.leadComm.openCallChoice(cnt.id, btn);
            } else {
                window.location.href = `tel:${cnt.cleanPhone || cnt.phone}`;
            }
        },

        openWhatsApp: function (cntId, btn) {
            const cnt = contacts.find(c => c.id == cntId);
            if (!cnt) return;
            if (window.leadComm && typeof window.leadComm.openWhatsApp === 'function') {
                const leadAdapter = {
                    id: cnt.id,
                    name: cnt.name,
                    company: cnt.company,
                    phone: cnt.phone,
                    cleanPhone: cnt.cleanPhone,
                    assigneeColor: cnt.ownerColor
                };
                window.activeLeadForComm = leadAdapter;
                window.leadComm.openWhatsApp(cnt.id, btn);
            } else {
                const firstName = cnt.firstName || cnt.name.split(' ')[0] || 'there';
                const msg = encodeURIComponent(`Hi ${firstName}, contacting you from NexFlow CRM.`);
                window.open(`https://wa.me/${cnt.cleanPhone || cnt.phone}?text=${msg}`, '_blank');
            }
        },

        openEmail: function (cntId, btn) {
            const cnt = contacts.find(c => c.id == cntId);
            if (!cnt) return;
            if (window.leadComm && typeof window.leadComm.openEmail === 'function') {
                const leadAdapter = {
                    id: cnt.id,
                    name: cnt.name,
                    company: cnt.company,
                    email: cnt.email,
                    phone: cnt.phone,
                    status: cnt.relationship,
                    value: cnt.dealsValue,
                    lastActivity: cnt.lastActivity,
                    assigneeColor: cnt.ownerColor
                };
                window.activeLeadForComm = leadAdapter;
                window.leadComm.openEmail(cnt.id, btn);
            } else {
                window.location.href = `mailto:${cnt.email}?subject=Follow-up%20from%20NexFlow%20CRM`;
            }
        },

        // Floating Row Actions Menu
        openRowMenu: function (e, cntId) {
            e.stopPropagation();
            const cnt = contacts.find(c => c.id == cntId);
            if (!cnt) return;

            const menu = document.getElementById('contactsRowDropdown');
            if (!menu) return;

            const rect = e.currentTarget.getBoundingClientRect();
            menu.style.left = `${Math.min(rect.left - 140, window.innerWidth - 200)}px`;
            menu.style.top = `${rect.bottom + 4}px`;

            menu.innerHTML = `
                <button type="button" class="contracts-row-item" onclick="window.contactsApp.openDrawer('${cnt.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    View Details
                </button>
                <button type="button" class="contracts-row-item" onclick="window.contactsApp.openEditModal('${cnt.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Contact
                </button>
                <button type="button" class="contracts-row-item" onclick="window.contactsApp.openAddTaskModal('${cnt.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    Add Task
                </button>
                <button type="button" class="contracts-row-item" onclick="window.contactsApp.openAddNoteModal('${cnt.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                    Add Note
                </button>
                <button type="button" class="contracts-row-item" onclick="window.contactsApp.openChangeOwnerModal('${cnt.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Change Owner
                </button>
                <button type="button" class="contracts-row-item" onclick="window.contactsApp.openChangeRelationshipModal('${cnt.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    Change Relationship
                </button>
                <button type="button" class="contracts-row-item" onclick="window.contactsApp.toggleActive('${cnt.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    ${((cnt.isActive !== undefined) ? Boolean(cnt.isActive) : (Number(cnt.is_active) === 1)) ? 'Mark as Inactive' : 'Mark as Active'}
                </button>
                <div class="contracts-row-divider"></div>
                <button type="button" class="contracts-row-item danger" onclick="window.contactsApp.openDeleteModal('${cnt.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Delete Contact
                </button>
            `;

            menu.classList.add('show');
        },

        // Details Drawer
        openDrawer: async function (cntId, initialTab = 'overview') {
            activeContactId = cntId;

            const drawer = document.getElementById('contactDrawer');
            const breadcrumb = document.getElementById('drawerBreadcrumb');
            const title = document.getElementById('drawerContactTitle');
            const isAlreadyOpen = drawer && drawer.classList.contains('show');

            if (drawer) {
                drawer.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            const body = document.getElementById('drawerContactBody');
            if (body && !isAlreadyOpen) {
                body.innerHTML = '<div style="text-align:center;padding:48px 16px;color:var(--text-secondary);">Loading contact details...</div>';
            }

            const res = await apiRequest(`api/contacts.php?action=drawer_data&id=${cntId}`);
            if (res.success && res.data && res.data.contact) {
                activeContactData = res.data.contact;
                if (breadcrumb) breadcrumb.textContent = `Contacts / ${activeContactData.name}`;
                if (title) title.textContent = activeContactData.name;
                this.switchDrawerTab(initialTab);
            } else {
                if (body && !isAlreadyOpen) body.innerHTML = '<div style="text-align:center;padding:48px 16px;color:var(--text-secondary);">Could not load contact details.</div>';
            }
        },

        closeDrawer: function () {
            const drawer = document.getElementById('contactDrawer');
            if (drawer) {
                drawer.classList.remove('show');
                document.body.style.overflow = '';
            }
        },

        toggleDrawerHeaderMenu: function (e) {
            if (e) e.stopPropagation();
            const menu = document.getElementById('contactsDrawerMenu');
            if (menu) menu.classList.toggle('show');
        },

        closeDrawerHeaderMenu: function () {
            const menu = document.getElementById('contactsDrawerMenu');
            if (menu) menu.classList.remove('show');
        },

        switchDrawerTab: function (tabName) {
            document.querySelectorAll('.drawer-tab').forEach(t => {
                t.classList.toggle('active', t.getAttribute('data-tab') === tabName);
            });

            const cnt = activeContactData;
            const body = document.getElementById('drawerContactBody');
            if (!body || !cnt) return;

            const relBadge = cnt.relationship === 'Customer' ? 'badge-customer' :
                             cnt.relationship === 'Prospect' ? 'badge-prospect' :
                             cnt.relationship === 'Partner' ? 'badge-partner' : 'badge-inactive';

            if (tabName === 'overview') {
                const initials = cnt.firstName ? (cnt.firstName[0] + (cnt.lastName ? cnt.lastName[0] : '')) : (cnt.name ? cnt.name[0] : 'C');
                const email = cnt.email || '—';
                const phone = cnt.phone || '—';
                const whatsapp = cnt.whatsapp || phone;
                const location = cnt.location || '—';

                body.innerHTML = `
                    <!-- Contact Profile Header Section -->
                    <div style="display: flex; align-items: flex-start; gap: 14px; margin-bottom: 18px;">
                        <div class="avatar avatar-lg" style="background-color: ${cnt.ownerColor || '#7C3AED'}; font-size: 16px; width: 46px; height: 46px;">${initials}</div>
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <h4 style="font-size: 17px; font-weight: 700; color: var(--text-heading); font-family: var(--font-family); margin: 0;">${escapeHtml(cnt.name)}</h4>
                                    ${cnt.isPrimary ? `<span class="badge-primary-contact" title="Primary Contact"><span class="badge-primary-star">★</span> Primary</span>` : ''}
                                </div>
                                <span class="badge-rel ${relBadge}">
                                    ● ${cnt.relationship || 'Prospect'}
                                </span>
                            </div>
                            <p style="font-size: 13px; color: var(--text-secondary); margin-top: 2px;">
                                ${escapeHtml(cnt.jobTitle || 'Contact')} ${cnt.company ? 'at <strong>' + escapeHtml(cnt.company) + '</strong>' : ''}
                            </p>
                            <div style="display: flex; align-items: center; gap: 12px; font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                <span>📍 ${escapeHtml(location)}</span>
                                <span>👤 Owner: <strong>${escapeHtml(cnt.owner || 'Unassigned')}</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- Primary Action Buttons Row -->
                    <div class="contact-primary-actions">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.openScheduleMeetingModal('${cnt.id}')">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Schedule Meeting
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.openAddTaskModal('${cnt.id}')">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                            Add Task
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.openAddNoteModal('${cnt.id}')">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Add Note
                        </button>
                    </div>

                    <!-- Contact Information Card -->
                    <div class="drawer-info-card">
                        <div class="drawer-card-title">Contact Information</div>
                        <div class="drawer-info-grid">
                            <div class="drawer-info-row full-width">
                                <span class="drawer-info-label">Work Email</span>
                                <div class="drawer-info-val">
                                    <span>${escapeHtml(email)}</span>
                                    ${email !== '—' ? `
                                    <button type="button" class="drawer-copy-btn" title="Copy Email" onclick="window.contactsApp.copyToClipboard('${email}', 'Work Email')">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                                    </button>` : ''}
                                </div>
                            </div>

                            <div class="drawer-info-row">
                                <span class="drawer-info-label">Direct Phone</span>
                                <div class="drawer-info-val">
                                    <span>${escapeHtml(phone)}</span>
                                    ${phone !== '—' ? `
                                    <button type="button" class="drawer-copy-btn" title="Copy Phone" onclick="window.contactsApp.copyToClipboard('${phone}', 'Direct Phone')">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                                    </button>` : ''}
                                </div>
                            </div>

                            <div class="drawer-info-row">
                                <span class="drawer-info-label">WhatsApp</span>
                                <div class="drawer-info-val">
                                    <span>${escapeHtml(whatsapp)}</span>
                                    ${whatsapp !== '—' ? `
                                    <button type="button" class="drawer-copy-btn" title="Copy WhatsApp" onclick="window.contactsApp.copyToClipboard('${whatsapp}', 'WhatsApp')">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                                    </button>` : ''}
                                </div>
                            </div>

                            <div class="drawer-info-row">
                                <span class="drawer-info-label">Location</span>
                                <div class="drawer-info-val">${escapeHtml(location)}</div>
                            </div>

                            <div class="drawer-info-row">
                                <span class="drawer-info-label">Preferred Channel</span>
                                <div class="drawer-info-val">
                                    ${(cnt.preferred_channel || cnt.preferredChannel) ? `
                                    <span class="status-badge" style="background:#EFF4FF;color:#2563EB;font-size:11px;font-weight:600;padding:2px 8px;border-radius:12px;">
                                        ${escapeHtml(cnt.preferred_channel || cnt.preferredChannel)}
                                    </span>` : '<span style="color:var(--text-muted);">—</span>'}
                                </div>
                            </div>

                            <div class="drawer-info-row full-width">
                                <span class="drawer-info-label">LinkedIn</span>
                                <div class="drawer-info-val">
                                    ${cnt.linkedin ? `
                                    <a href="${cnt.linkedin.startsWith('http') ? escapeHtml(cnt.linkedin) : 'https://' + escapeHtml(cnt.linkedin)}" target="_blank" style="color:var(--primary);text-decoration:none;">
                                        ${escapeHtml(cnt.linkedin)}
                                    </a>` : '<span style="color:var(--text-muted);">—</span>'}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CRM Details Card -->
                    <div class="drawer-info-card">
                        <div class="drawer-card-title">CRM Details</div>
                        <div class="drawer-info-grid">
                            <div class="drawer-info-row">
                                <span class="drawer-info-label">Relationship</span>
                                <div class="drawer-info-val">${escapeHtml(cnt.relationship || 'Prospect')}</div>
                            </div>
                            <div class="drawer-info-row">
                                <span class="drawer-info-label">Assigned Owner</span>
                                <div class="drawer-info-val">${escapeHtml(cnt.owner || 'Unassigned')}</div>
                            </div>
                            <div class="drawer-info-row">
                                <span class="drawer-info-label">Lead Source</span>
                                <div class="drawer-info-val">${escapeHtml(cnt.source || '—')}</div>
                            </div>
                            <div class="drawer-info-row">
                                <span class="drawer-info-label">Created Date</span>
                                <div class="drawer-info-val">${escapeHtml(cnt.createdAt || '—')}</div>
                            </div>
                        </div>
                    </div>
                `;
            } else if (tabName === 'activity') {
                const activities = cnt.activities || [];
                body.innerHTML = `
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                        <h4 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0;">Interaction Timeline</h4>
                        <button type="button" class="btn btn-primary btn-xs" onclick="window.contactsApp.openLogActivityModal('${cnt.id}')">+ Log Activity</button>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        ${activities.length === 0 ? '<p style="font-size:13px;color:var(--text-secondary);margin:0;">No logged activities for this contact.</p>' : activities.map(a => `
                            <div class="contact-activity-card">
                                <div class="avatar avatar-xs" style="background:#2563EB;color:#FFF;font-size:10px;font-weight:700;flex-shrink:0;">
                                    ${(a.user || 'U').split(' ').map(n=>n[0]).join('')}
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;">
                                        <span style="font-weight:600;font-size:13px;color:var(--text-heading);">${escapeHtml(a.user)}</span>
                                        <span style="font-size:11px;color:var(--text-muted);">${escapeHtml(a.date)}</span>
                                    </div>
                                    <div style="font-size:13px;color:var(--text-body);margin-top:3px;line-height:1.4;">${escapeHtml(a.text)}</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else if (tabName === 'deals') {
                const deals = cnt.deals || [];
                const totalCount = deals.length;
                const wonVal = deals.filter(d => d.stage === 'Won').reduce((acc, d) => acc + (d.value || 0), 0);
                const openVal = deals.filter(d => d.stage !== 'Won' && d.stage !== 'Lost').reduce((acc, d) => acc + (d.value || 0), 0);

                body.innerHTML = `
                    <div class="contact-deals-grid">
                        <div class="contact-deal-summary-box">
                            <span style="font-size:11px;color:var(--text-secondary);font-weight:600;">TOTAL DEALS</span>
                            <span style="font-size:16px;font-weight:700;color:var(--text-heading);margin-top:2px;">${totalCount}</span>
                        </div>
                        <div class="contact-deal-summary-box">
                            <span style="font-size:11px;color:var(--text-secondary);font-weight:600;">WON VALUE</span>
                            <span style="font-size:16px;font-weight:700;color:#047857;margin-top:2px;">$${wonVal.toLocaleString()}</span>
                        </div>
                        <div class="contact-deal-summary-box">
                            <span style="font-size:11px;color:var(--text-secondary);font-weight:600;">OPEN PIPELINE</span>
                            <span style="font-size:16px;font-weight:700;color:#2563EB;margin-top:2px;">$${openVal.toLocaleString()}</span>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                        <h4 style="font-size:13.5px;font-weight:700;color:var(--text-heading);margin:0;">Associated Deals</h4>
                        <button type="button" class="btn btn-primary btn-xs" onclick="window.contactsApp.openCreateDealModal('${cnt.id}')">+ Create Deal</button>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        ${deals.length === 0 ? '<p style="font-size:13px;color:var(--text-secondary);">No associated deals found for this contact.</p>' : deals.map(d => `
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:#F8FAFC;border:1px solid var(--border-divider);border-radius:8px;">
                                <div>
                                    <div style="font-weight:600;font-size:13px;color:var(--text-heading);">${escapeHtml(d.name)}</div>
                                    <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;">
                                        Target Close: ${escapeHtml(d.closeDate || 'Open')}
                                        ${d.prob !== null ? `<span style="margin-left:6px;background:#EFF6FF;color:#1D4ED8;padding:1px 5px;border-radius:10px;font-weight:600;">${d.prob}%</span>` : ''}
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-weight:700;color:var(--text-heading);">$${(d.value || 0).toLocaleString()}</div>
                                    <span class="badge ${d.stage === 'Won' ? 'badge-success' : 'badge-primary'}" style="font-size:10px;margin-top:2px;">${escapeHtml(d.stage)}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else if (tabName === 'tasks') {
                const tasks = cnt.tasks || [];
                body.innerHTML = `
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                        <h4 style="font-size:14px;font-weight:700;color:var(--text-heading);margin:0;">Contact Tasks</h4>
                        <button type="button" class="btn btn-primary btn-xs" onclick="window.contactsApp.openAddTaskModal('${cnt.id}')">+ Add Task</button>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        ${tasks.length === 0 ? '<p style="font-size:13px;color:var(--text-secondary);">No active tasks for this contact.</p>' : tasks.map(t => `
                            <div class="contact-task-item">
                                <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
                                    <input type="checkbox" class="input-checkbox" ${t.completed ? 'checked' : ''} onchange="window.contactsApp.toggleTaskComplete('${cnt.id}', '${t.id}', this.checked)">
                                    <span style="font-size:13px;font-weight:500;${t.completed ? 'text-decoration:line-through;color:var(--text-muted);' : 'color:var(--text-heading);'}">${escapeHtml(t.title)}</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span class="badge ${t.priority === 'High' ? 'badge-danger' : 'badge-primary'}" style="font-size:10px;">${t.priority}</span>
                                    <span style="font-size:11px;color:var(--text-muted);">${t.dueDate}</span>
                                    <button type="button" class="btn btn-ghost btn-xs" title="Edit Task" style="padding:2px 4px;" onclick="window.contactsApp.openEditTaskModal('${cnt.id}', '${t.id}')">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button type="button" class="btn btn-ghost btn-xs" title="Delete Task" style="padding:2px 4px;color:#DC2626;" onclick="window.contactsApp.deleteTask('${cnt.id}', '${t.id}')">
                                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                    </button>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else if (tabName === 'notes') {
                body.innerHTML = this.renderNotesTab(cnt);
            }
        },

        renderNotesTab: function (cnt) {
            return `
                <!-- Search & Add Note Toolbar -->
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;">
                    <div class="cnt-note-search-wrapper">
                        <span class="cnt-note-search-icon">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </span>
                        <input type="search" class="input-control input-sm cnt-note-search-input" id="cntNoteSearchInput" placeholder="Search notes..." value="${escapeHtml(notesSearchQuery)}" oninput="window.contactsApp.onNoteSearchInput(this.value, '${cnt.id}')">
                    </div>
                    <button type="button" class="btn btn-primary btn-xs" onclick="window.contactsApp.openAddNoteModal('${cnt.id}')">
                        + Add Note
                    </button>
                </div>

                <div id="cntNotesListContainer">
                    ${this.renderNotesListHtml(cnt)}
                </div>
            `;
        },

        renderNotesListHtml: function (cnt) {
            const notes = cnt.notes || [];
            const query = (notesSearchQuery || '').toLowerCase().trim();
            const filtered = notes.filter(n => !query || (n.text && n.text.toLowerCase().includes(query)) || (n.content && n.content.toLowerCase().includes(query)) || (n.author && n.author.toLowerCase().includes(query)));

            const pinned = filtered.filter(n => n.pinned);
            const recent = filtered.filter(n => !n.pinned);

            if (filtered.length === 0) {
                return `
                    <div style="text-align:center;padding:32px 16px;color:var(--text-muted);font-size:13px;">
                        ${query ? 'No notes found. Try a different search term.' : 'No notes found. Click <strong>"+ Add Note"</strong> to record a meeting summary or quick note.'}
                    </div>
                `;
            }

            let html = '';
            if (pinned.length > 0) {
                html += `
                    <div class="task-group-title" style="color:var(--primary);margin:14px 0 8px;">📌 Pinned Notes</div>
                    ${pinned.map(n => this.renderNoteCardHtml(n, cnt.id)).join('')}
                `;
            }
            if (recent.length > 0) {
                html += `
                    <div class="task-group-title" style="margin:14px 0 8px;">Recent Notes</div>
                    ${recent.map(n => this.renderNoteCardHtml(n, cnt.id)).join('')}
                `;
            }
            return html;
        },

        renderNoteCardHtml: function (n, cntId) {
            const noteText = n.text || n.content || '';
            const formattedDate = formatNoteDate(n.date);

            return `
                <div class="note-card ${n.pinned ? 'pinned' : ''}">
                    <div class="note-card-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                        <span class="note-author" style="font-size:12.5px;font-weight:600;color:var(--text-heading);">${escapeHtml(n.author)}</span>
                        <span class="note-date" style="font-size:11px;color:var(--text-muted);font-weight:400;">${escapeHtml(formattedDate)}</span>
                    </div>
                    <div class="note-content" style="font-size:13px;color:var(--text-body);line-height:1.45;white-space:pre-wrap;word-break:break-word;">${escapeHtml(noteText)}</div>
                    <div class="note-actions" style="display:flex;align-items:center;gap:6px;margin-top:8px;justify-content:flex-end;">
                        <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;color:${n.pinned ? 'var(--primary)' : 'var(--text-muted)'};" title="${n.pinned ? 'Unpin' : 'Pin'}" onclick="window.contactsApp.togglePinNote('${cntId}', '${n.id}')">
                            📌 ${n.pinned ? 'Pinned' : 'Pin'}
                        </button>
                        <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;" title="Edit" onclick="window.contactsApp.openEditNoteModal('${cntId}', '${n.id}')">Edit</button>
                        <button type="button" class="btn btn-ghost btn-xs" style="padding:2px 6px;color:#F04438;" title="Delete" onclick="window.contactsApp.deleteNote('${cntId}', '${n.id}')">Delete</button>
                    </div>
                </div>
            `;
        },

        onNoteSearchInput: function (val, cntId) {
            notesSearchQuery = val;
            this.renderNotesList(cntId);
        },

        renderNotesList: function (cntId) {
            const targetId = cntId || activeContactId;
            const container = document.getElementById('cntNotesListContainer');
            if (container && activeContactData) {
                container.innerHTML = this.renderNotesListHtml(activeContactData);
            }
        },

        setupCompanyAutocomplete: function (inputEl, hiddenIdEl, dropdownEl) {
            if (!inputEl || !hiddenIdEl || !dropdownEl) return;

            let debounceTimer = null;
            let abortCtrl = null;
            let items = [];
            let activeIndex = -1;

            function closeDropdown() {
                dropdownEl.style.display = 'none';
                dropdownEl.innerHTML = '';
                items = [];
                activeIndex = -1;
            }

            function renderDropdown(query, results) {
                dropdownEl.innerHTML = '';
                items = [];
                activeIndex = -1;

                const trimmedQ = query.trim();
                let hasExactMatch = false;
                (results || []).forEach(comp => {
                    if (comp.name && comp.name.toLowerCase() === trimmedQ.toLowerCase()) {
                        hasExactMatch = true;
                    }
                });

                (results || []).forEach((comp, idx) => {
                    items.push({ id: comp.id, name: comp.name, isCreate: false });
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'nexflow-autocomplete-item';
                    itemDiv.setAttribute('data-index', idx);
                    itemDiv.innerHTML = `<span>${escapeHtml(comp.name)}</span>`;
                    itemDiv.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        selectCompany(comp.id, comp.name);
                    });
                    dropdownEl.appendChild(itemDiv);
                });

                // User directive: show + Create "query" if no exact match
                if (!hasExactMatch && trimmedQ.length > 0) {
                    const createIdx = items.length;
                    items.push({ id: null, name: trimmedQ, isCreate: true });
                    const createDiv = document.createElement('div');
                    createDiv.className = 'nexflow-autocomplete-item create-option';
                    createDiv.setAttribute('data-index', createIdx);
                    createDiv.innerHTML = `<span>+ Create "${escapeHtml(trimmedQ)}"</span>`;
                    createDiv.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        setPendingCompany(trimmedQ);
                    });
                    dropdownEl.appendChild(createDiv);
                }

                if (items.length > 0) {
                    dropdownEl.style.display = 'block';
                } else {
                    closeDropdown();
                }
            }

            function selectCompany(id, name) {
                inputEl.value = name;
                hiddenIdEl.value = id || '';
                closeDropdown();
            }

            function setPendingCompany(name) {
                inputEl.value = name;
                hiddenIdEl.value = ''; // Pending company creation on contact submit
                closeDropdown();
            }

            function updateHighlight() {
                const domItems = dropdownEl.querySelectorAll('.nexflow-autocomplete-item');
                domItems.forEach((el, idx) => {
                    if (idx === activeIndex) {
                        el.classList.add('active');
                        el.scrollIntoView({ block: 'nearest' });
                    } else {
                        el.classList.remove('active');
                    }
                });
            }

            inputEl.addEventListener('input', function () {
                // Invalidate company_id when user edits text
                hiddenIdEl.value = '';
                const q = inputEl.value.trim();

                // USER RULE: 0-1 chars -> closed, 2+ -> backend search
                if (q.length < 2) {
                    if (abortCtrl) abortCtrl.abort();
                    clearTimeout(debounceTimer);
                    closeDropdown();
                    return;
                }

                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(async () => {
                    if (abortCtrl) abortCtrl.abort();
                    abortCtrl = new AbortController();

                    try {
                        const res = await fetch(`api/companies.php?action=search&q=${encodeURIComponent(q)}`, {
                            signal: abortCtrl.signal
                        });
                        const data = await res.json();
                        if (data.success && data.data) {
                            renderDropdown(q, data.data.companies || []);
                        }
                    } catch (err) {
                        if (err.name !== 'AbortError') {
                            console.error('Company search error:', err);
                        }
                    }
                }, 250);
            });

            inputEl.addEventListener('keydown', function (e) {
                if (dropdownEl.style.display === 'none' || items.length === 0) {
                    return;
                }

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIndex = (activeIndex + 1) % items.length;
                    updateHighlight();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIndex = (activeIndex - 1 + items.length) % items.length;
                    updateHighlight();
                } else if (e.key === 'Enter') {
                    if (activeIndex >= 0 && activeIndex < items.length) {
                        e.preventDefault();
                        e.stopPropagation();
                        const item = items[activeIndex];
                        if (item.isCreate) {
                            setPendingCompany(item.name);
                        } else {
                            selectCompany(item.id, item.name);
                        }
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    closeDropdown();
                }
            });

            document.addEventListener('click', function (e) {
                if (!inputEl.contains(e.target) && !dropdownEl.contains(e.target)) {
                    closeDropdown();
                }
            });
        },

        // Modals: Add Contact
        openAddContactModal: function () {
            const ownerOpts = availableOwners.map(u => `<option value="${u.id}">${escapeHtml(u.name)}</option>`).join('');

            const html = `
                <div class="contacts-modal-header">
                    <h3 class="contacts-modal-title">Add New Contact</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.contactsApp.submitAddContact(event)">
                    <div class="contacts-modal-body">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">First Name *</label>
                                <input type="text" class="input-control" id="newCntFirst" placeholder="e.g. Alex" required autofocus>
                            </div>
                            <div>
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="input-control" id="newCntLast" placeholder="e.g. Morgan" required>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div style="position:relative;">
                                <label class="form-label">Company Name *</label>
                                <input type="text" class="input-control" id="newCntCompany" placeholder="e.g. Acme Corp" required autocomplete="off">
                                <input type="hidden" id="newCntCompanyId" value="">
                                <div class="nexflow-autocomplete-dropdown" id="newCntCompanyDropdown" style="display:none;"></div>
                            </div>
                            <div>
                                <label class="form-label">Job Title</label>
                                <input type="text" class="input-control" id="newCntJob" placeholder="e.g. VP of Sales">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Email Address *</label>
                                <input type="email" class="input-control" id="newCntEmail" placeholder="alex@company.com" required>
                            </div>
                            <div>
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="input-control" id="newCntPhone" placeholder="+1 (555) 000-0000">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Relationship</label>
                                <select class="input-control" id="newCntRel">
                                    <option value="Customer">Customer</option>
                                    <option value="Prospect" selected>Prospect</option>
                                    <option value="Partner">Partner</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Owner</label>
                                <select class="input-control" id="newCntOwner">
                                    <option value="">Unassigned</option>
                                    ${ownerOpts}
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Source</label>
                                <input type="text" class="input-control" id="newCntSource" placeholder="Website, Referral..." value="Website">
                            </div>
                            <div>
                                <label class="form-label">Location</label>
                                <input type="text" class="input-control" id="newCntLoc" placeholder="e.g. San Francisco, CA">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Preferred Channel</label>
                                <select class="input-control" id="newCntPreferredChannel">
                                    <option value="">Select channel...</option>
                                    <option value="Email">Email</option>
                                    <option value="Phone">Phone</option>
                                    <option value="WhatsApp">WhatsApp</option>
                                    <option value="SMS">SMS</option>
                                    <option value="Video Call">Video Call</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">LinkedIn</label>
                                <input type="text" class="input-control" id="newCntLinkedin" placeholder="https://www.linkedin.com/in/...">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:12px;margin-bottom:0;">
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-body);cursor:pointer;">
                                <input type="checkbox" id="newCntIsPrimary" value="1"> Set as Primary Contact
                            </label>
                        </div>
                    </div>
                    <div class="contacts-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Create Contact</button>
                    </div>
                </form>
            `;
            this.openModal(html);
            this.setupCompanyAutocomplete(
                document.getElementById('newCntCompany'),
                document.getElementById('newCntCompanyId'),
                document.getElementById('newCntCompanyDropdown')
            );
        },

        submitAddContact: async function (e) {
            e.preventDefault();
            const first = document.getElementById('newCntFirst')?.value.trim();
            const last = document.getElementById('newCntLast')?.value.trim();
            const company = document.getElementById('newCntCompany')?.value.trim();
            const companyId = document.getElementById('newCntCompanyId')?.value.trim();
            const isPrimary = document.getElementById('newCntIsPrimary')?.checked ? 1 : 0;
            const job = document.getElementById('newCntJob')?.value.trim();
            const email = document.getElementById('newCntEmail')?.value.trim();
            const phone = document.getElementById('newCntPhone')?.value.trim();
            const rel = document.getElementById('newCntRel')?.value;
            const ownerId = document.getElementById('newCntOwner')?.value;
            const source = document.getElementById('newCntSource')?.value.trim();
            const loc = document.getElementById('newCntLoc')?.value.trim();
            const prefChannel = document.getElementById('newCntPreferredChannel')?.value.trim();
            const linkedin = document.getElementById('newCntLinkedin')?.value.trim();

            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create',
                    first_name: first,
                    last_name: last,
                    name: `${first} ${last}`.trim(),
                    company_name: company,
                    company_id: companyId ? parseInt(companyId, 10) : null,
                    is_primary: isPrimary,
                    job_title: job,
                    email: email,
                    phone: phone,
                    preferred_channel: prefChannel || null,
                    linkedin: linkedin || null,
                    relationship: rel,
                    owner_id: ownerId || null,
                    source: source,
                    location: loc
                })
            });

            if (res.success) {
                this.closeModal();
                this.showToast('Contact created successfully.', 'success');
                fetchContacts();
            } else {
                this.showToast(res.message || 'Failed to create contact.', 'error');
            }
        },

        // Edit Contact Modal
        openEditModal: function (cntId) {
            this.closeDrawerHeaderMenu();
            const targetId = (typeof cntId === 'string' || typeof cntId === 'number') ? cntId : activeContactId;
            const cnt = (activeContactData && activeContactData.id == targetId) ? activeContactData : contacts.find(c => c.id == targetId);
            if (!cnt) return;

            const currentOwnerId = cnt.ownerId || cnt.owner_id || '';
            const ownerOpts = availableOwners.map(u => 
                `<option value="${u.id}" ${String(u.id) === String(currentOwnerId) ? 'selected' : ''}>${escapeHtml(u.name)}</option>`
            ).join('');

            const prefChannelVal = cnt.preferred_channel || cnt.preferredChannel || '';
            const standardChannels = ['Email', 'Phone', 'WhatsApp', 'SMS', 'Video Call', 'Other'];
            let customChannelOption = '';
            if (prefChannelVal && !standardChannels.includes(prefChannelVal)) {
                customChannelOption = `<option value="${escapeHtml(prefChannelVal)}" selected>${escapeHtml(prefChannelVal)}</option>`;
            }

            const html = `
                <div class="contacts-modal-header">
                    <h3 class="contacts-modal-title">Edit Contact: ${escapeHtml(cnt.name)}</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.contactsApp.submitEditContact(event, '${cnt.id}')">
                    <div class="contacts-modal-body">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="input-control" id="editCntName" value="${escapeHtml(cnt.name)}" required>
                            </div>
                            <div>
                                <label class="form-label">Job Title</label>
                                <input type="text" class="input-control" id="editCntJob" value="${escapeHtml(cnt.jobTitle || cnt.job_title || '')}">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div style="position:relative;">
                                <label class="form-label">Company *</label>
                                <input type="text" class="input-control" id="editCntCompany" value="${escapeHtml(cnt.company || cnt.company_name || '')}" required autocomplete="off">
                                <input type="hidden" id="editCntCompanyId" value="${(cnt.companyId || cnt.company_id) ? escapeHtml(String(cnt.companyId || cnt.company_id)) : ''}">
                                <div class="nexflow-autocomplete-dropdown" id="editCntCompanyDropdown" style="display:none;"></div>
                            </div>
                            <div>
                                <label class="form-label">Email *</label>
                                <input type="email" class="input-control" id="editCntEmail" value="${escapeHtml(cnt.email || '')}" required>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Phone</label>
                                <input type="text" class="input-control" id="editCntPhone" value="${escapeHtml(cnt.phone || '')}">
                            </div>
                            <div>
                                <label class="form-label">Relationship</label>
                                <select class="input-control" id="editCntRel">
                                    <option value="Customer" ${cnt.relationship === 'Customer' ? 'selected' : ''}>Customer</option>
                                    <option value="Prospect" ${cnt.relationship === 'Prospect' ? 'selected' : ''}>Prospect</option>
                                    <option value="Partner" ${cnt.relationship === 'Partner' ? 'selected' : ''}>Partner</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Owner</label>
                                <select class="input-control" id="editCntOwner">
                                    <option value="">Unassigned</option>
                                    ${ownerOpts}
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Source</label>
                                <input type="text" class="input-control" id="editCntSource" value="${escapeHtml(cnt.source || '')}" placeholder="Website, Referral...">
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Location</label>
                            <input type="text" class="input-control" id="editCntLoc" value="${escapeHtml(cnt.location || '')}" placeholder="e.g. San Francisco, CA">
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Preferred Channel</label>
                                <select class="input-control" id="editCntPreferredChannel">
                                    <option value="">Select channel...</option>
                                    <option value="Email" ${prefChannelVal === 'Email' ? 'selected' : ''}>Email</option>
                                    <option value="Phone" ${prefChannelVal === 'Phone' ? 'selected' : ''}>Phone</option>
                                    <option value="WhatsApp" ${prefChannelVal === 'WhatsApp' ? 'selected' : ''}>WhatsApp</option>
                                    <option value="SMS" ${prefChannelVal === 'SMS' ? 'selected' : ''}>SMS</option>
                                    <option value="Video Call" ${prefChannelVal === 'Video Call' ? 'selected' : ''}>Video Call</option>
                                    <option value="Other" ${prefChannelVal === 'Other' ? 'selected' : ''}>Other</option>
                                    ${customChannelOption}
                                </select>
                            </div>
                            <div>
                                <label class="form-label">LinkedIn</label>
                                <input type="text" class="input-control" id="editCntLinkedin" value="${escapeHtml(cnt.linkedin || '')}" placeholder="https://www.linkedin.com/in/...">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:12px;margin-bottom:0;">
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-body);cursor:pointer;">
                                <input type="checkbox" id="editCntIsPrimary" value="1" ${cnt.isPrimary ? 'checked' : ''}> Set as Primary Contact
                            </label>
                        </div>
                    </div>
                    <div class="contacts-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                </form>
            `;
            this.openModal(html);

            this.setupCompanyAutocomplete(
                document.getElementById('editCntCompany'),
                document.getElementById('editCntCompanyId'),
                document.getElementById('editCntCompanyDropdown')
            );
        },

        submitEditContact: async function (e, cntId) {
            e.preventDefault();
            const name = document.getElementById('editCntName')?.value.trim();
            const job = document.getElementById('editCntJob')?.value.trim();
            const company = document.getElementById('editCntCompany')?.value.trim();
            const companyId = document.getElementById('editCntCompanyId')?.value.trim();
            const isPrimary = document.getElementById('editCntIsPrimary')?.checked ? 1 : 0;
            const email = document.getElementById('editCntEmail')?.value.trim();
            const phone = document.getElementById('editCntPhone')?.value.trim();
            const rel = document.getElementById('editCntRel')?.value;
            const ownerId = document.getElementById('editCntOwner')?.value;
            const source = document.getElementById('editCntSource')?.value.trim();
            const location = document.getElementById('editCntLoc')?.value.trim();
            const prefChannel = document.getElementById('editCntPreferredChannel')?.value.trim();
            const linkedin = document.getElementById('editCntLinkedin')?.value.trim();

            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update',
                    id: cntId,
                    name: name,
                    job_title: job,
                    company_name: company,
                    company_id: companyId ? parseInt(companyId, 10) : null,
                    is_primary: isPrimary,
                    email: email,
                    phone: phone,
                    preferred_channel: prefChannel || null,
                    linkedin: linkedin || null,
                    relationship: rel,
                    owner_id: ownerId ? parseInt(ownerId, 10) : null,
                    source: source,
                    location: location
                })
            });

            if (res.success) {
                this.closeModal();
                this.showToast('Contact updated successfully.', 'success');
                fetchContacts();
                if (activeContactId == cntId) {
                    this.openDrawer(cntId);
                }
            } else {
                this.showToast(res.message || 'Failed to update contact.', 'error');
            }
        },

        // Change Relationship Modal
        openChangeRelationshipModal: function (cntId) {
            this.closeDrawerHeaderMenu();
            const targetId = cntId || activeContactId;
            const cnt = (activeContactData && activeContactData.id == targetId) ? activeContactData : contacts.find(c => c.id == targetId);
            if (!cnt) return;

            const html = `
                <div class="contacts-modal-header">
                    <h3 class="contacts-modal-title">Change Relationship</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="contacts-modal-body">
                    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px;">
                        Select relationship status for <strong>${escapeHtml(cnt.name)}</strong>:
                    </p>
                    <select class="input-control" id="changeRelSelect">
                        <option value="Customer" ${cnt.relationship === 'Customer' ? 'selected' : ''}>Customer</option>
                        <option value="Prospect" ${cnt.relationship === 'Prospect' ? 'selected' : ''}>Prospect</option>
                        <option value="Partner" ${cnt.relationship === 'Partner' ? 'selected' : ''}>Partner</option>
                    </select>
                </div>
                <div class="contacts-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.contactsApp.confirmChangeRelationship('${cnt.id}')">Update</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmChangeRelationship: async function (cntId) {
            const rel = document.getElementById('changeRelSelect')?.value;
            if (!rel) return;

            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'change_relationship',
                    id: cntId,
                    relationship: rel
                })
            });

            if (res.success) {
                this.closeModal();
                this.showToast(`Relationship changed to ${rel}.`, 'success');
                fetchContacts();
                if (activeContactId == cntId) {
                    this.openDrawer(cntId);
                }
            } else {
                this.showToast(res.message || 'Failed to update relationship.', 'error');
            }
        },

        // Change Owner Modal
        openChangeOwnerModal: function (cntId) {
            this.closeDrawerHeaderMenu();
            const targetId = cntId || activeContactId;
            const cnt = (activeContactData && activeContactData.id == targetId) ? activeContactData : contacts.find(c => c.id == targetId);
            if (!cnt) return;

            const ownerOpts = availableOwners.map(u => `<option value="${u.id}" ${cnt.ownerId == u.id ? 'selected' : ''}>${escapeHtml(u.name)}</option>`).join('');

            const html = `
                <div class="contacts-modal-header">
                    <h3 class="contacts-modal-title">Change Assigned Owner</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="contacts-modal-body">
                    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px;">
                        Reassign contact <strong>${escapeHtml(cnt.name)}</strong> to:
                    </p>
                    <select class="input-control" id="changeOwnerSelect">
                        <option value="">Unassigned</option>
                        ${ownerOpts}
                    </select>
                </div>
                <div class="contacts-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.contactsApp.confirmChangeOwner('${cnt.id}')">Reassign</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmChangeOwner: async function (cntId) {
            const ownerId = document.getElementById('changeOwnerSelect')?.value;

            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'change_owner',
                    id: cntId,
                    owner_id: ownerId || null
                })
            });

            if (res.success) {
                this.closeModal();
                this.showToast('Contact owner updated.', 'success');
                fetchContacts();
                if (activeContactId == cntId) {
                    this.openDrawer(cntId);
                }
            } else {
                this.showToast(res.message || 'Failed to update owner.', 'error');
            }
        },

        // Delete Contact Modal
        openDeleteModal: function (cntId) {
            this.closeDrawerHeaderMenu();
            const targetId = cntId || activeContactId;
            const cnt = (activeContactData && activeContactData.id == targetId) ? activeContactData : contacts.find(c => c.id == targetId);
            if (!cnt) return;

            const html = `
                <div class="contacts-modal-header danger">
                    <h3 class="contacts-modal-title" style="color:#DC2626;">Delete Contact</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="contacts-modal-body">
                    <p style="font-size:13.5px;color:var(--text-body);margin-bottom:8px;">
                        Are you sure you want to permanently delete <strong>${escapeHtml(cnt.name)}</strong>?
                    </p>
                    <p style="font-size:12px;color:var(--text-muted);margin:0;">
                        This action will remove the contact and its associated notes and tasks from MySQL.
                    </p>
                </div>
                <div class="contacts-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" style="background:#DC2626;color:#FFF;" onclick="window.contactsApp.confirmDelete('${cnt.id}')">Delete Contact</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmDelete: async function (cntId) {
            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    id: cntId
                })
            });

            if (res.success) {
                this.closeModal();
                this.closeDrawer();
                this.showToast(res.message, 'success');
                fetchContacts();
            } else {
                this.showToast(res.message || 'Failed to delete contact.', 'error');
            }
        },

        toggleActive: async function (cntId) {
            this.closeDrawerHeaderMenu();
            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'toggle_active',
                    id: cntId
                })
            });

            if (res.success) {
                this.showToast(res.message || 'Contact status updated.', 'info');
                fetchContacts();
                if (activeContactId == cntId) {
                    this.openDrawer(cntId);
                }
            } else {
                this.showToast(res.message || 'Failed to update status.', 'error');
            }
        },

        // Schedule Meeting Modal
        openScheduleMeetingModal: function (triggerOrCntId) {
            const cntId = (typeof triggerOrCntId === 'string' || typeof triggerOrCntId === 'number') ? triggerOrCntId : activeContactId;
            const cnt = (activeContactData && activeContactData.id == cntId) ? activeContactData : contacts.find(c => c.id == cntId);
            if (!cnt) return;

            const html = `
                <div class="contacts-modal-header">
                    <h3 class="contacts-modal-title">Schedule Meeting</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.contactsApp.submitScheduleMeeting(event, '${cnt.id}')">
                    <div class="contacts-modal-body">
                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label">Contact</label>
                            <input type="text" class="input-control" value="${escapeHtml(cnt.name)} ${cnt.company ? '(' + escapeHtml(cnt.company) + ')' : ''}" disabled style="background:#F8FAFC;cursor:not-allowed;">
                        </div>
                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label">Meeting Title *</label>
                            <input type="text" class="input-control" id="cntMeetingTitle" placeholder="e.g. Executive Strategy Alignment Sync" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Date *</label>
                                <input type="date" class="input-control" id="cntMeetingDate" value="${new Date().toISOString().split('T')[0]}" required>
                            </div>
                            <div>
                                <label class="form-label">Start Time *</label>
                                <input type="time" class="input-control" id="cntMeetingTime" value="10:00" required>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Duration</label>
                                <select class="input-control" id="cntMeetingDuration">
                                    <option value="15">15 mins</option>
                                    <option value="30" selected>30 mins</option>
                                    <option value="45">45 mins</option>
                                    <option value="60">1 hour</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Meeting Type</label>
                                <select class="input-control" id="cntMeetingType">
                                    <option value="Video Call" selected>Video Call (Google Meet)</option>
                                    <option value="Phone Call">Phone Call</option>
                                    <option value="In Person">In Person Meeting</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Description / Agenda</label>
                            <textarea class="input-control" id="cntMeetingDesc" style="height:60px;padding:8px 12px;" placeholder="Add agenda or preparation notes..."></textarea>
                        </div>
                    </div>
                    <div class="contacts-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Schedule Meeting</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitScheduleMeeting: async function (e, cntId) {
            e.preventDefault();
            const title = document.getElementById('cntMeetingTitle')?.value.trim();
            const date = document.getElementById('cntMeetingDate')?.value;
            const time = document.getElementById('cntMeetingTime')?.value;
            const duration = document.getElementById('cntMeetingDuration')?.value;
            const desc = document.getElementById('cntMeetingDesc')?.value.trim();

            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'schedule_meeting',
                    contact_id: cntId,
                    title: title,
                    date: date,
                    time: time,
                    duration: duration,
                    description: desc
                })
            });

            if (res.success) {
                this.closeModal();
                this.showToast('Meeting scheduled successfully.', 'success');
                if (activeContactId == cntId) {
                    this.openDrawer(cntId);
                }
            } else {
                this.showToast(res.message || 'Failed to schedule meeting.', 'error');
            }
        },

        // Tasks Handlers
        openAddTaskModal: function (cntId) {
            const targetId = cntId || activeContactId;
            const cnt = (activeContactData && activeContactData.id == targetId) ? activeContactData : contacts.find(c => c.id == targetId);
            if (!cnt) return;

            const html = `
                <div class="contacts-modal-header">
                    <h3 class="contacts-modal-title">Add Task</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.contactsApp.submitAddTask(event, '${cnt.id}')">
                    <div class="contacts-modal-body">
                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label">Task Title *</label>
                            <input type="text" class="input-control" id="taskTitle" placeholder="e.g. Follow up on Q4 contract" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Priority</label>
                                <select class="input-control" id="taskPriority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Due Date</label>
                                <input type="date" class="input-control" id="taskDueDate">
                            </div>
                        </div>
                    </div>
                    <div class="contacts-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Add Task</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitAddTask: async function (e, cntId) {
            e.preventDefault();
            const title = document.getElementById('taskTitle')?.value.trim();
            const priority = document.getElementById('taskPriority')?.value;
            const dueDate = document.getElementById('taskDueDate')?.value;

            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add_task',
                    contact_id: cntId,
                    title: title,
                    priority: priority,
                    due_date: dueDate
                })
            });

            if (res.success) {
                this.closeModal();
                this.showToast('Task added successfully.', 'success');
                if (activeContactId == cntId) {
                    await this.openDrawer(cntId, 'tasks');
                }
            } else {
                this.showToast(res.message || 'Failed to add task.', 'error');
            }
        },

        openEditTaskModal: function (cntId, taskId) {
            const cnt = activeContactData;
            if (!cnt || !cnt.tasks) return;
            const task = cnt.tasks.find(t => t.id == taskId);
            if (!task) return;

            let rawDate = '';
            if (task.due_date) {
                rawDate = String(task.due_date).split(' ')[0].split('T')[0];
            } else if (task.dueDate && task.dueDate !== 'No due date') {
                if (/^\d{4}-\d{2}-\d{2}/.test(task.dueDate)) {
                    rawDate = task.dueDate.split(' ')[0].split('T')[0];
                } else {
                    const parsed = new Date(task.dueDate);
                    if (!isNaN(parsed.getTime())) {
                        const y = parsed.getFullYear();
                        const m = String(parsed.getMonth() + 1).padStart(2, '0');
                        const d = String(parsed.getDate()).padStart(2, '0');
                        rawDate = `${y}-${m}-${d}`;
                    }
                }
            }

            const priorityLower = (task.priority || 'medium').toLowerCase();

            const html = `
                <div class="contacts-modal-header">
                    <h3 class="contacts-modal-title">Edit Task</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.contactsApp.submitEditTask(event, '${cnt.id}', '${task.id}')">
                    <div class="contacts-modal-body">
                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label">Task Title *</label>
                            <input type="text" class="input-control" id="editTaskTitle" value="${escapeHtml(task.title)}" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Priority</label>
                                <select class="input-control" id="editTaskPriority">
                                    <option value="low" ${priorityLower === 'low' ? 'selected' : ''}>Low</option>
                                    <option value="medium" ${priorityLower === 'medium' ? 'selected' : ''}>Medium</option>
                                    <option value="high" ${priorityLower === 'high' ? 'selected' : ''}>High</option>
                                    <option value="urgent" ${priorityLower === 'urgent' ? 'selected' : ''}>Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Due Date</label>
                                <input type="date" class="input-control" id="editTaskDueDate" value="${escapeHtml(rawDate)}">
                            </div>
                        </div>
                    </div>
                    <div class="contacts-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitEditTask: async function (e, cntId, taskId) {
            e.preventDefault();
            const title = document.getElementById('editTaskTitle')?.value.trim();
            const priority = document.getElementById('editTaskPriority')?.value;
            const dueDate = document.getElementById('editTaskDueDate')?.value;

            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'edit_task',
                    task_id: taskId,
                    title: title,
                    priority: priority,
                    due_date: dueDate || null
                })
            });

            if (res.success) {
                this.closeModal();
                this.showToast('Task updated successfully.', 'success');
                if (activeContactId == cntId) {
                    await this.openDrawer(cntId, 'tasks');
                }
            } else {
                this.showToast(res.message || 'Failed to update task.', 'error');
            }
        },

        toggleTaskComplete: async function (cntId, taskId, isComplete) {
            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'toggle_task',
                    task_id: taskId,
                    completed: isComplete
                })
            });

            if (res.success && activeContactId == cntId) {
                await this.openDrawer(cntId, 'tasks');
            }
        },

        deleteTask: function (cntId, taskId) {
            const doDelete = async () => {
                const res = await apiRequest('api/contacts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_task',
                        task_id: taskId
                    })
                });

                if (res.success) {
                    this.showToast('Task deleted.', 'info');
                    if (activeContactId == cntId) {
                        await this.openDrawer(cntId, 'tasks');
                    }
                } else {
                    this.showToast(res.message || 'Failed to delete task.', 'error');
                }
            };

            if (typeof window.showConfirmModal === 'function') {
                window.showConfirmModal({
                    title: 'Delete Task',
                    message: 'Are you sure you want to delete this task?\nThis action cannot be undone.',
                    type: 'danger',
                    confirmText: 'Delete Task',
                    cancelText: 'Cancel',
                    onConfirm: doDelete
                });
            } else {
                const html = `
                    <div class="contacts-modal-header danger">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:8px;background:#FEE2E2;color:#DC2626;display:flex;align-items:center;justify-content:center;">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                            </div>
                            <h3 class="contacts-modal-title" style="color:#DC2626;margin:0;">Delete Task</h3>
                        </div>
                        <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="contacts-modal-body">
                        <p style="font-size:13.5px;color:var(--text-body);margin-bottom:6px;">
                            Are you sure you want to delete this task?
                        </p>
                        <p style="font-size:12px;color:var(--text-muted);margin:0;">
                            This action cannot be undone.
                        </p>
                    </div>
                    <div class="contacts-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                        <button type="button" class="btn btn-danger btn-sm" style="background:#DC2626;color:#FFF;" id="confirmDeleteTaskBtn">Delete Task</button>
                    </div>
                `;
                this.openModal(html);
                const btn = document.getElementById('confirmDeleteTaskBtn');
                if (btn) {
                    btn.onclick = async () => {
                        this.closeModal();
                        await doDelete();
                    };
                }
            }
        },

        // Notes Handlers
        openAddNoteModal: function (triggerOrCntId) {
            const targetId = (typeof triggerOrCntId === 'string' || typeof triggerOrCntId === 'number') ? triggerOrCntId : activeContactId;
            const cnt = (activeContactData && activeContactData.id == targetId) ? activeContactData : contacts.find(c => c.id == targetId);
            if (!cnt) return;

            const html = `
                <div class="contacts-modal-header">
                    <h3 class="contacts-modal-title">Add Note</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.contactsApp.submitAddNoteModal(event, '${cnt.id}')">
                    <div class="contacts-modal-body">
                        <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:12px;">
                            Note for <strong>${escapeHtml(cnt.name)}</strong>
                        </div>
                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label">Note Content *</label>
                            <textarea class="input-control" id="modalNoteText" rows="4" style="height:100px;resize:none;padding:10px 12px;" placeholder="Write a note about this contact..." required></textarea>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-body);cursor:pointer;">
                                <input type="checkbox" id="modalNotePin"> Pin this note to top
                            </label>
                        </div>
                    </div>
                    <div class="contacts-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Note</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        openAddNoteModalOverview: function (cntId) {
            this.openAddNoteModal(cntId);
        },

        submitAddNoteModal: async function (e, cntId) {
            e.preventDefault();
            const text = document.getElementById('modalNoteText')?.value.trim();
            const pinned = document.getElementById('modalNotePin')?.checked || false;

            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'add_note',
                    contact_id: cntId,
                    content: text,
                    is_pinned: pinned
                })
            });

            if (res.success) {
                this.closeModal();
                this.showToast(pinned ? 'Note pinned to top.' : 'Note saved successfully.', 'success');
                if (activeContactId == cntId) {
                    await this.openDrawer(cntId, 'notes');
                }
            } else {
                this.showToast(res.message || 'Failed to save note.', 'error');
            }
        },

        openEditNoteModal: function (cntId, noteId) {
            const cnt = activeContactData;
            if (!cnt || !cnt.notes) return;
            const note = cnt.notes.find(n => n.id == noteId);
            if (!note) return;

            const noteText = note.text || note.content || '';

            const html = `
                <div class="contacts-modal-header">
                    <h3 class="contacts-modal-title">Edit Note</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.contactsApp.submitEditNoteModal(event, '${cnt.id}', '${note.id}')">
                    <div class="contacts-modal-body">
                        <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:12px;">
                            Editing note for <strong>${escapeHtml(cnt.name)}</strong>
                        </div>
                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label">Note Content *</label>
                            <textarea class="input-control" id="editModalNoteText" rows="4" style="height:100px;resize:none;padding:10px 12px;" required>${escapeHtml(noteText)}</textarea>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-body);cursor:pointer;">
                                <input type="checkbox" id="editModalNotePin" ${note.pinned ? 'checked' : ''}> Pin this note to top
                            </label>
                        </div>
                    </div>
                    <div class="contacts-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitEditNoteModal: async function (e, cntId, noteId) {
            e.preventDefault();
            const text = document.getElementById('editModalNoteText')?.value.trim();
            const pinned = document.getElementById('editModalNotePin')?.checked || false;

            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'edit_note',
                    note_id: noteId,
                    content: text,
                    is_pinned: pinned
                })
            });

            if (res.success) {
                this.closeModal();
                this.showToast('Note updated successfully.', 'success');
                if (activeContactId == cntId) {
                    await this.openDrawer(cntId, 'notes');
                }
            } else {
                this.showToast(res.message || 'Failed to update note.', 'error');
            }
        },

        togglePinNote: async function (cntId, noteId) {
            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'toggle_pin_note',
                    note_id: noteId
                })
            });

            if (res.success) {
                this.showToast(res.data && res.data.pinned ? 'Note pinned.' : 'Note unpinned.', 'info');
                if (activeContactId == cntId) {
                    await this.openDrawer(cntId, 'notes');
                }
            } else {
                this.showToast(res.message || 'Failed to toggle pin.', 'error');
            }
        },

        deleteNote: function (cntId, noteId) {
            const doDelete = async () => {
                const res = await apiRequest('api/contacts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_note',
                        note_id: noteId
                    })
                });

                if (res.success) {
                    this.showToast('Note deleted.', 'info');
                    if (activeContactId == cntId) {
                        await this.openDrawer(cntId, 'notes');
                    }
                } else {
                    this.showToast(res.message || 'Failed to delete note.', 'error');
                }
            };

            if (typeof window.showConfirmModal === 'function') {
                window.showConfirmModal({
                    title: 'Delete Note',
                    message: 'Are you sure you want to delete this note?\nThis action cannot be undone.',
                    type: 'danger',
                    confirmText: 'Delete Note',
                    cancelText: 'Cancel',
                    onConfirm: doDelete
                });
            } else {
                const html = `
                    <div class="contacts-modal-header danger">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:8px;background:#FEE2E2;color:#DC2626;display:flex;align-items:center;justify-content:center;">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                            </div>
                            <h3 class="contacts-modal-title" style="color:#DC2626;margin:0;">Delete Note</h3>
                        </div>
                        <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="contacts-modal-body">
                        <p style="font-size:13.5px;color:var(--text-body);margin-bottom:6px;">
                            Are you sure you want to delete this note?
                        </p>
                        <p style="font-size:12px;color:var(--text-muted);margin:0;">
                            This action cannot be undone.
                        </p>
                    </div>
                    <div class="contacts-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                        <button type="button" class="btn btn-danger btn-sm" style="background:#DC2626;color:#FFF;" id="confirmDeleteNoteBtn">Delete Note</button>
                    </div>
                `;
                this.openModal(html);
                const btn = document.getElementById('confirmDeleteNoteBtn');
                if (btn) {
                    btn.onclick = async () => {
                        this.closeModal();
                        await doDelete();
                    };
                }
            }
        },

        // Activity Handlers
        openLogActivityModal: function (cntId) {
            const targetId = cntId || activeContactId;
            const cnt = (activeContactData && activeContactData.id == targetId) ? activeContactData : contacts.find(c => c.id == targetId);
            if (!cnt) return;

            const html = `
                <div class="contacts-modal-header">
                    <h3 class="contacts-modal-title">Log Activity</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.contactsApp.submitLogActivity(event, '${cnt.id}')">
                    <div class="contacts-modal-body">
                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label">Activity Type</label>
                            <select class="input-control" id="logType">
                                <option value="Call">Call</option>
                                <option value="Meeting">Meeting</option>
                                <option value="Email">Email</option>
                                <option value="WhatsApp">WhatsApp Message</option>
                                <option value="Note">General Note</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Summary / Description *</label>
                            <textarea class="input-control" id="logSummary" rows="3" style="height:80px;resize:none;padding:8px 12px;" placeholder="Details of the interaction..." required></textarea>
                        </div>
                    </div>
                    <div class="contacts-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Log Activity</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitLogActivity: async function (e, cntId) {
            e.preventDefault();
            const type = document.getElementById('logType')?.value;
            const summary = document.getElementById('logSummary')?.value.trim();

            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'log_activity',
                    contact_id: cntId,
                    type: type,
                    summary: summary
                })
            });

            if (res.success) {
                this.closeModal();
                this.showToast('Activity logged successfully.', 'success');
                if (activeContactId == cntId) {
                    await this.openDrawer(cntId);
                    this.switchDrawerTab('activity');
                }
            } else {
                this.showToast(res.message || 'Failed to log activity.', 'error');
            }
        },

        // Deals Handlers
        openCreateDealModal: function (cntId) {
            const targetId = cntId || activeContactId;
            const cnt = (activeContactData && activeContactData.id == targetId) ? activeContactData : contacts.find(c => c.id == targetId);
            if (!cnt) return;

            const html = `
                <div class="contacts-modal-header">
                    <h3 class="contacts-modal-title">Create Associated Deal</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.contactsApp.submitCreateDeal(event, '${cnt.id}')">
                    <div class="contacts-modal-body">
                        <div class="form-group" style="margin-bottom:12px;">
                            <label class="form-label">Deal Name *</label>
                            <input type="text" class="input-control" id="newDealName" placeholder="e.g. Enterprise License Expansion" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Deal Value ($) *</label>
                                <input type="number" step="0.01" class="input-control" id="newDealValue" placeholder="25000" required>
                            </div>
                            <div>
                                <label class="form-label">Pipeline Stage</label>
                                <select class="input-control" id="newDealStage">
                                    <option value="Prospect" selected>Prospect</option>
                                    <option value="Qualified">Qualified</option>
                                    <option value="Proposal">Proposal</option>
                                    <option value="Negotiation">Negotiation</option>
                                    <option value="Won">Won</option>
                                    <option value="Lost">Lost</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Probability (%)</label>
                                <input type="number" min="0" max="100" class="input-control" id="newDealProb" value="50">
                            </div>
                            <div>
                                <label class="form-label">Target Close Date</label>
                                <input type="date" class="input-control" id="newDealCloseDate">
                            </div>
                        </div>
                    </div>
                    <div class="contacts-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Create Deal</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitCreateDeal: async function (e, cntId) {
            e.preventDefault();
            const name = document.getElementById('newDealName')?.value.trim();
            const val = document.getElementById('newDealValue')?.value;
            const stage = document.getElementById('newDealStage')?.value;
            const prob = document.getElementById('newDealProb')?.value;
            const closeDate = document.getElementById('newDealCloseDate')?.value;

            const res = await apiRequest('api/contacts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create_deal',
                    contact_id: cntId,
                    name: name,
                    value: val,
                    stage: stage,
                    probability: prob,
                    close_date: closeDate
                })
            });

            if (res.success) {
                this.closeModal();
                this.showToast('Deal created successfully.', 'success');
                fetchContacts();
                if (activeContactId == cntId) {
                    await this.openDrawer(cntId);
                    this.switchDrawerTab('deals');
                }
            } else {
                this.showToast(res.message || 'Failed to create deal.', 'error');
            }
        },

        // Import & Export Handlers
        openImportModal: function () {
            const html = `
                <div class="contacts-modal-header">
                    <h3 class="contacts-modal-title">Import Contacts (CSV)</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.contactsApp.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="contacts-modal-body">
                    <p style="font-size:13px;color:var(--text-body);margin-bottom:12px;">
                        Upload a CSV file containing contacts (Name, Email, Company, Job Title, Phone, Relationship, Owner).
                    </p>
                    <div style="border:2px dashed var(--border-divider);border-radius:8px;padding:24px;text-align:center;background:#F8FAFC;">
                        <input type="file" id="contactsCsvFileInput" accept=".csv" style="display:block;margin:0 auto;">
                    </div>
                </div>
                <div class="contacts-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.contactsApp.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.contactsApp.confirmImportCSV()">Upload &amp; Import</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmImportCSV: async function () {
            const fileInput = document.getElementById('contactsCsvFileInput');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                this.showToast('Please select a CSV file first.', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'import_csv');
            formData.append('csv_file', fileInput.files[0]);

            try {
                const res = await fetch('api/contacts.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    this.closeModal();
                    this.showToast(data.message || 'Contacts imported successfully.', 'success');
                    fetchContacts();
                } else {
                    this.showToast(data.message || 'Import failed.', 'error');
                }
            } catch (e) {
                this.showToast('Failed to upload CSV file.', 'error');
            }
        },

        exportCSV: function () {
            window.location.href = 'api/contacts.php?action=export_csv';
            this.showToast('Exporting contacts to CSV...', 'info');
        },

        // Modal Utilities
        openModal: function (htmlContent) {
            const overlay = document.getElementById('contactsActionModal');
            const card = document.getElementById('contactsActionModalCard');
            if (overlay && card) {
                card.innerHTML = htmlContent;
                overlay.style.display = 'flex';
                overlay.classList.add('show');
            }
        },

        closeModal: function () {
            const overlay = document.getElementById('contactsActionModal');
            if (overlay) {
                overlay.classList.remove('show');
                overlay.style.display = 'none';
            }
        },

        showToast: function (msg, type = 'info') {
            const container = document.getElementById('contactsToastContainer');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `contacts-toast ${type}`;
            toast.style.cssText = 'background:#1E293B;color:#FFF;padding:10px 16px;border-radius:8px;font-size:13px;box-shadow:0 4px 12px rgba(0,0,0,0.15);margin-bottom:8px;display:flex;align-items:center;gap:8px;animation:fadeIn 0.2s ease;';

            let icon = 'ℹ️';
            if (type === 'success') {
                icon = '✅';
                toast.style.background = '#065F46';
            } else if (type === 'warning') {
                icon = '⚠️';
                toast.style.background = '#92400E';
            } else if (type === 'error') {
                icon = '❌';
                toast.style.background = '#991B1B';
            }

            toast.innerHTML = `<span>${icon}</span><span>${escapeHtml(msg)}</span>`;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        },

        copyToClipboard: function (text, label) {
            if (!text || text === '—') return;
            navigator.clipboard.writeText(text).then(() => {
                this.showToast(`${label} copied to clipboard.`, 'success');
            }).catch(() => {
                this.showToast('Could not copy to clipboard.', 'error');
            });
        }
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.contactsApp.init());
    } else {
        window.contactsApp.init();
    }
})();