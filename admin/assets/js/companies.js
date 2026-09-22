/**
 * NexFlow CRM Companies Management Controller JavaScript
 * Real MySQL Backend Integration via admin/api/companies.php.
 * Strictly 0 mock data, 100% MySQL source of truth.
 * Handles search, tabs, sorting, list/grid view, bulk actions, pagination,
 * WAI-ARIA Company Details drawer (Overview, Contacts, Deals, Activity, Tasks, Notes),
 * Add/Edit company drawer, filter panel, task creation, and note pinning.
 */

(function() {
    const ACCOUNT_VIEW_STORAGE_KEY = "NexFlow.companies.accountView";
    const DENSITY_STORAGE_KEY = "NexFlow.companies.density";

    let allCompanies = [];
    let filteredCompanies = [];
    let cardFilter = "All";
    let filterIndustry = "All";
    let filterOwner = "All";
    let filterDealsRange = "All";
    let activeAccountView = loadAccountViewState();
    let currentDensity = loadDensityState();
    let searchQuery = "";
    let sortBy = "name-asc";
    let viewMode = "list"; // 'list' or 'grid'
    let currentPage = 1;
    let rowsPerPage = 8;
    let totalRecords = 0;
    let totalPages = 1;
    let selectedCompanyIds = new Set();
    let activeCompanyForDetails = null;
    let activeDrawerData = null;
    let activeTabName = "overview";
    let lastFocusedElement = null;
    let drawerScrollPosition = 0;
    let companyModalTrigger = null;
    let compNotesSearchQuery = "";
    let activeCompanyModal = null;
    let activeCompanyModalData = null;

    let availableOwners = [];
    let availableContacts = [];

    function loadAccountViewState() {
        try {
            const saved = localStorage.getItem(ACCOUNT_VIEW_STORAGE_KEY);
            if (saved && ["all", "my", "key", "high"].includes(saved)) {
                return saved;
            }
        } catch (e) {
            console.warn("Unable to read accountView from localStorage:", e);
        }
        return "all";
    }

    function saveAccountViewState(view) {
        activeAccountView = view;
        try {
            localStorage.setItem(ACCOUNT_VIEW_STORAGE_KEY, view);
        } catch (e) {
            console.warn("Unable to save accountView to localStorage:", e);
        }
    }

    function loadDensityState() {
        try {
            const saved = localStorage.getItem(DENSITY_STORAGE_KEY);
            if (saved && ["comfortable", "compact"].includes(saved)) {
                return saved;
            }
        } catch (e) {
            console.warn("Unable to read density from localStorage:", e);
        }
        return "comfortable";
    }

    function applyDensityUI() {
        const pageEl = document.querySelector(".companies-page") || document.body;
        if (currentDensity === "compact") {
            pageEl.classList.add("compact-density");
        } else {
            pageEl.classList.remove("compact-density");
        }

        const textEl = document.getElementById("densityItemText");
        if (textEl) {
            textEl.textContent = (currentDensity === "comfortable") ? "Compact Density" : "Comfortable Density";
        }
    }

    // --- API Data Fetching ---
    function fetchCompanies(callback) {
        const params = new URLSearchParams({
            action: 'list',
            search: searchQuery,
            card_filter: cardFilter,
            industry: filterIndustry,
            owner: filterOwner,
            deals_range: filterDealsRange,
            account_view: activeAccountView,
            sort: sortBy,
            page: currentPage,
            page_size: rowsPerPage
        });

        fetch('api/companies.php?' + params.toString())
            .then(res => res.json())
            .then(res => {
                if (!res.success) {
                    console.error('Failed to fetch companies:', res.message);
                    showCompaniesToast(res.message || 'Error loading companies.');
                    return;
                }

                const data = res.data || {};
                allCompanies = data.companies || [];
                filteredCompanies = allCompanies;
                availableOwners = data.owners || [];
                availableContacts = data.contacts || [];

                const pag = data.pagination || {};
                totalRecords = pag.total || 0;
                totalPages = pag.totalPages || 1;
                currentPage = pag.page || 1;

                // Update KPIs
                const kpis = data.kpis || {};
                updateKPIBadges(kpis, totalRecords);

                renderCompanies();
                renderActiveChips();
                announceResultCount();

                if (typeof callback === 'function') callback();
            })
            .catch(err => {
                console.error('Companies fetch error:', err);
                showCompaniesToast('Network error while loading companies.');
            });
    }

    function updateKPIBadges(kpis, total) {
        const totalBadge = document.getElementById("companiesTotalCountBadge");
        if (totalBadge) totalBadge.textContent = `${total} companies`;

        const elTotal = document.getElementById("summaryTotalCount");
        if (elTotal) elTotal.textContent = (kpis.total || 0).toLocaleString();

        const elNew = document.getElementById("summaryNewCompanies");
        if (elNew) elNew.textContent = (kpis.new || 0).toLocaleString();

        const elOpenOpps = document.getElementById("summaryOpenOpps");
        if (elOpenOpps) elOpenOpps.textContent = (kpis.openDeals || 0).toLocaleString();

        const elCust = document.getElementById("summaryCustomersCount");
        if (elCust) elCust.textContent = (kpis.customers || 0).toLocaleString();

        const elProsp = document.getElementById("summaryProspectsCount");
        if (elProsp) elProsp.textContent = (kpis.prospects || 0).toLocaleString();

        const elPart = document.getElementById("summaryPartnersCount");
        if (elPart) elPart.textContent = (kpis.partners || 0).toLocaleString();

        const elInact = document.getElementById("summaryInactiveCompanies");
        if (elInact) elInact.textContent = (kpis.inactive || 0).toLocaleString();
    }

    function announceResultCount() {
        let liveRegion = document.getElementById("companiesLiveRegion");
        if (!liveRegion) {
            liveRegion = document.createElement("div");
            liveRegion.id = "companiesLiveRegion";
            liveRegion.setAttribute("aria-live", "polite");
            liveRegion.setAttribute("aria-atomic", "true");
            liveRegion.className = "sr-only";
            document.body.appendChild(liveRegion);
        }
        liveRegion.textContent = `Showing ${filteredCompanies.length} of ${totalRecords} companies.`;
    }

    function setupCompanyPhoneSearch() {
        const phoneInput = document.getElementById("addCompanyContactPhone");
        const nameInput = document.getElementById("addCompanyContactName");
        const selIdInput = document.getElementById("addCompanySelectedContactId");
        const emailInput = document.getElementById("addCompanyContactEmail");
        const emailGroup = document.getElementById("addCompanyNewEmailGroup");
        const helper = document.getElementById("addCompanyContactHelper");
        const dropdownEl = document.getElementById("addCompanyPhoneDropdown");

        if (!phoneInput || !dropdownEl) return;

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

        function selectContact(cnt) {
            phoneInput.value = cnt.phone || cnt.clean_phone;
            if (nameInput) {
                nameInput.value = cnt.name;
                nameInput.readOnly = true;
            }
            if (selIdInput) {
                selIdInput.value = cnt.id;
            }
            if (emailInput && cnt.email) {
                emailInput.value = cnt.email;
            }
            if (helper) helper.style.display = 'none';
            if (emailGroup) emailGroup.style.display = 'none';
            closeDropdown();
        }

        function renderDropdown(contacts) {
            dropdownEl.innerHTML = '';
            items = contacts || [];
            activeIndex = -1;

            if (items.length === 0) {
                // CASE B: No contact found
                if (selIdInput) selIdInput.value = '';
                if (nameInput) {
                    nameInput.readOnly = false;
                }
                if (helper) helper.style.display = 'block';
                if (emailGroup) emailGroup.style.display = 'block';
                closeDropdown();
                return;
            }

            // CASE A: Contacts found
            items.forEach((cnt, idx) => {
                const itemDiv = document.createElement('div');
                itemDiv.className = 'nexflow-autocomplete-item';
                itemDiv.setAttribute('data-index', idx);
                itemDiv.style.cssText = 'padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-light, #eee); display: flex; flex-direction: column; gap: 2px;';

                const compText = (cnt.companies && cnt.companies.length > 0) ? cnt.companies.join(', ') : 'No company';

                itemDiv.innerHTML = `
                    <div style="font-weight: 600; font-size: 13px; color: var(--text-heading);">${escapeHtml(cnt.name)}</div>
                    <div style="font-size: 11px; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                        <span>${escapeHtml(cnt.phone || cnt.clean_phone)}</span>
                        <span>•</span>
                        <span style="color: var(--primary);">${escapeHtml(compText)}</span>
                    </div>
                `;
                itemDiv.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    selectContact(cnt);
                });
                dropdownEl.appendChild(itemDiv);
            });

            dropdownEl.style.display = 'block';
        }

        function updateHighlight() {
            const domItems = dropdownEl.querySelectorAll('.nexflow-autocomplete-item');
            domItems.forEach((el, idx) => {
                if (idx === activeIndex) {
                    el.classList.add('active');
                    el.style.backgroundColor = 'var(--bg-subtle, #f3f4f6)';
                    el.scrollIntoView({ block: 'nearest' });
                } else {
                    el.classList.remove('active');
                    el.style.backgroundColor = '';
                }
            });
        }

        phoneInput.addEventListener('input', function () {
            const raw = phoneInput.value;
            const clean = raw.replace(/[^0-9]/g, '');

            // If user cleared/changed phone after having selected an existing contact, reset selection
            if (selIdInput && selIdInput.value) {
                selIdInput.value = '';
                if (nameInput) nameInput.readOnly = false;
            }

            if (clean.length < 3) {
                if (abortCtrl) abortCtrl.abort();
                clearTimeout(debounceTimer);
                closeDropdown();
                if (helper) helper.style.display = 'none';
                if (emailGroup) emailGroup.style.display = 'none';
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
                if (abortCtrl) abortCtrl.abort();
                abortCtrl = new AbortController();

                try {
                    const res = await fetch(`api/companies.php?action=search_contacts_by_phone&query=${encodeURIComponent(clean)}`, {
                        signal: abortCtrl.signal
                    });
                    const data = await res.json();
                    if (data.success && data.data) {
                        renderDropdown(data.data.contacts || []);
                    }
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        console.error('Phone search error:', err);
                    }
                }
            }, 250);
        });

        // If user manually types into the contact name field, consider it a new contact
        if (nameInput) {
            nameInput.addEventListener('input', function() {
                if (selIdInput && selIdInput.value) {
                    selIdInput.value = '';
                    nameInput.readOnly = false;
                }
                if (helper) helper.style.display = 'block';
                if (emailGroup) emailGroup.style.display = 'block';
            });
        }

        phoneInput.addEventListener('keydown', function (e) {
            if (dropdownEl.style.display === 'none' || items.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
                updateHighlight();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = (activeIndex - 1 + items.length) % items.length;
                updateHighlight();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIndex >= 0 && activeIndex < items.length) {
                    selectContact(items[activeIndex]);
                }
            } else if (e.key === 'Escape') {
                closeDropdown();
            }
        });

        document.addEventListener('click', function (e) {
            if (!phoneInput.contains(e.target) && !dropdownEl.contains(e.target)) {
                closeDropdown();
            }
        });
    }

    function setupEventListeners() {
        // Search Input with debounce
        const searchInput = document.getElementById("companiesSearchInput");
        if (searchInput) {
            let debounceTimer = null;
            searchInput.addEventListener("input", function(e) {
                clearTimeout(debounceTimer);
                searchQuery = e.target.value.trim();
                debounceTimer = setTimeout(() => {
                    currentPage = 1;
                    fetchCompanies();
                }, 300);
            });
        }

        // Sort Dropdown
        const sortSelect = document.getElementById("companiesSortSelect");
        if (sortSelect) {
            sortSelect.addEventListener("change", function(e) {
                sortBy = e.target.value;
                currentPage = 1;
                fetchCompanies();
            });
        }

        // Account View Dropdown
        const viewsSelect = document.getElementById("companiesViewsSelect");
        if (viewsSelect) {
            viewsSelect.value = activeAccountView;
            viewsSelect.removeAttribute("onchange");
            viewsSelect.addEventListener("change", function(e) {
                saveAccountViewState(e.target.value);
                currentPage = 1;
                fetchCompanies();
            });
        }

        // Form Submit: Add / Edit Company
        const addForm = document.getElementById("addCompanyForm");
        if (addForm) {
            addForm.addEventListener("submit", submitAddOrEditCompany);
        }

        // Phone Search in Add/Edit Company Drawer
        setupCompanyPhoneSearch();

        // Form Submit: Add Task
        const addTaskForm = document.getElementById("compAddTaskForm");
        if (addTaskForm) {
            addTaskForm.addEventListener("submit", submitAddTaskModal);
        }

        // Keyboard navigation & Esc handling
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                const actionModal = document.getElementById("companyActionModal");
                if (actionModal && actionModal.classList.contains("is-open")) {
                    window.companiesApp.closeCompanyModal();
                    return;
                }

                const taskModal = document.getElementById("companyAddTaskModal");
                if (taskModal && taskModal.classList.contains("show")) {
                    window.companiesApp.closeAddTaskModal();
                    return;
                }

                const addDrawer = document.getElementById("companiesAddDrawer");
                if (addDrawer && addDrawer.classList.contains("show")) {
                    window.companiesApp.closeAddDrawer();
                    return;
                }

                const detailsDrawer = document.getElementById("companyDetailsDrawer");
                if (detailsDrawer && detailsDrawer.classList.contains("show")) {
                    window.companiesApp.closeDetails();
                    return;
                }

                const popover = document.getElementById("companiesFilterPopover");
                if (popover && popover.classList.contains("show")) {
                    window.companiesApp.closeFilterPopover();
                    return;
                }
            }
        });

        // Event delegation for view details triggers
        document.addEventListener("click", function(e) {
            const trigger = e.target.closest(".company-view-trigger");
            if (trigger) {
                const companyId = trigger.getAttribute("data-company-id");
                if (companyId) {
                    window.companiesApp.openDetails(parseInt(companyId, 10), trigger);
                }
            }

            const actionBtn = e.target.closest("[data-company-action]");
            if (actionBtn) {
                const action = actionBtn.getAttribute("data-company-action");
                const companyId = actionBtn.getAttribute("data-company-id");
                const dealId = actionBtn.getAttribute("data-deal-id");
                const contactId = actionBtn.getAttribute("data-contact-id");

                if (action && (companyId || dealId || contactId)) {
                    e.preventDefault();
                    window.companiesApp.openCompanyModal(action, dealId || contactId || companyId, actionBtn);
                }
            }
        });
    }

    function renderActiveChips() {
        const container = document.getElementById("companiesActiveChips");
        if (!container) return;

        const chips = [];
        if (filterIndustry !== "All") chips.push({ type: "industry", label: `Industry: ${filterIndustry}` });
        if (filterOwner !== "All") chips.push({ type: "owner", label: `Owner: ${filterOwner}` });
        if (filterDealsRange !== "All") chips.push({ type: "deals", label: `Deals: ${filterDealsRange}` });

        if (chips.length > 0) {
            container.classList.add("show");
            container.innerHTML = chips.map(c => `
                <span class="companies-filter-chip">
                    ${escapeHtml(c.label)}
                    <button type="button" onclick="window.companiesApp.removeFilterChip('${c.type}')">&times;</button>
                </span>
            `).join("") + '<button type="button" class="btn btn-ghost btn-xs" style="font-size: 11px; padding: 2px 6px;" onclick="window.companiesApp.resetFilters()">Clear all</button>';
        } else {
            container.classList.remove("show");
            container.innerHTML = "";
        }
    }

    function renderCompanies() {
        if (viewMode === "list") {
            document.getElementById("companiesListView").style.display = "block";
            document.getElementById("companiesGridView").style.display = "none";
            renderTableRows();
        } else {
            document.getElementById("companiesListView").style.display = "none";
            document.getElementById("companiesGridView").style.display = "grid";
            renderGridCards();
        }
        renderPagination();
    }

    function renderTableRows() {
        const tbody = document.getElementById("companiesTableBody");
        if (!tbody) return;

        if (filteredCompanies.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
                        <div style="font-size: 15px; font-weight: 600; color: var(--text-heading); margin-bottom: 4px;">No companies found</div>
                        <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">No companies match your current filters.</div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.resetFilters()">Clear all filters</button>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = filteredCompanies.map(c => {
            const isSelected = selectedCompanyIds.has(c.id);
            const relSlug = c.relationship ? c.relationship.toLowerCase() : "";

            return `
                <tr data-id="${c.id}">
                    <td data-column-id="company">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="avatar avatar-sm company-view-trigger" data-company-id="${c.id}" style="background-color: ${c.logoColor || '#7C3AED'}; flex-shrink: 0; cursor: pointer;">${escapeHtml(c.logoInitials || 'CO')}</div>
                            <div style="min-width: 0;">
                                <div class="company-name-clickable company-view-trigger" data-company-id="${c.id}">${escapeHtml(c.name)}</div>
                                <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(c.domain || '')}</div>
                            </div>
                        </div>
                    </td>
                    <td data-column-id="industry">
                        <div style="font-size: 12px; color: var(--text-heading); font-weight: 500;">${escapeHtml(c.industry || '—')}</div>
                        <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(c.size || '')}</div>
                    </td>
                    <td data-column-id="relationship">
                        ${c.relationship ? `
                            <span class="companies-badge ${relSlug}">
                                <span class="companies-badge-dot"></span>
                                ${escapeHtml(c.relationship)}
                            </span>
                        ` : '<span style="font-size: 12px; color: var(--text-muted);">—</span>'}
                    </td>
                    <td data-column-id="primary-contact">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div class="avatar avatar-xs" style="background-color: #7C3AED;">${escapeHtml(c.primaryContactAvatar || 'PC')}</div>
                            <div>
                                <div style="font-size: 12px; font-weight: 500; color: var(--text-heading);">${escapeHtml(c.primaryContactName || 'None')}</div>
                                <div style="font-size: 10.5px; color: var(--text-muted);">${escapeHtml(c.primaryContactTitle || '')}</div>
                            </div>
                        </div>
                    </td>
                    <td data-column-id="owner">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <div class="avatar avatar-xs" style="background-color: ${c.ownerColor || '#0284C7'};">${escapeHtml(c.ownerInitials || 'UN')}</div>
                            <span style="font-size: 12px; color: var(--text-secondary);">${escapeHtml(c.owner || 'Unassigned')}</span>
                        </div>
                    </td>
                    <td data-column-id="contacts">
                        <div style="font-weight: 600; color: var(--text-heading);">${c.contactsCount || 0} contacts</div>
                    </td>
                    <td data-column-id="open-deals">
                        <div style="font-weight: 600; color: var(--text-heading);">${c.openDealsCount || 0} deals</div>
                    </td>
                    <td data-column-id="pipeline-value">
                        <div style="font-weight: 700; color: var(--primary); font-variant-numeric: tabular-nums;">$${(c.openDealsValue || 0).toLocaleString()}</div>
                    </td>
                    <td data-column-id="last-activity" style="font-size: 12px; color: var(--text-muted); white-space: nowrap;">
                        ${escapeHtml(c.lastActivity || 'Never')}
                        <div style="font-size: 10px; color: var(--text-secondary);">${escapeHtml(c.lastActivityType || '')}</div>
                    </td>
                    <td data-column-id="actions" style="text-align: right;">
                        <button type="button" class="btn btn-ghost btn-xs company-view-trigger" data-company-id="${c.id}" aria-label="View company details">View</button>
                    </td>
                </tr>
            `;
        }).join("");
    }

    function renderGridCards() {
        const grid = document.getElementById("companiesGridView");
        if (!grid) return;

        if (filteredCompanies.length === 0) {
            grid.innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 48px 20px;">
                    <div style="font-size: 15px; font-weight: 600; color: var(--text-heading); margin-bottom: 4px;">No companies found</div>
                    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">No companies match your current filters.</div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.resetFilters()">Clear all filters</button>
                </div>
            `;
            return;
        }

        grid.innerHTML = filteredCompanies.map(c => {
            const relSlug = c.relationship ? c.relationship.toLowerCase() : "";
            const isSelected = selectedCompanyIds.has(c.id);

            return `
                <div class="companies-card ${isSelected ? 'selected' : ''}">
                    <div class="companies-card-top">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="avatar avatar-md company-view-trigger" data-company-id="${c.id}" style="background-color: ${c.logoColor || '#7C3AED'}; flex-shrink: 0; cursor: pointer;">${escapeHtml(c.logoInitials || 'CO')}</div>
                            <div>
                                <div class="company-name-clickable company-view-trigger" data-company-id="${c.id}" style="font-size: 14px;">${escapeHtml(c.name)}</div>
                                <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(c.domain || '')}</div>
                            </div>
                        </div>
                        ${c.relationship ? `<span class="companies-badge ${relSlug}">${escapeHtml(c.relationship)}</span>` : '<span style="font-size: 11px; color: var(--text-muted);">—</span>'}
                    </div>
                    <div class="companies-card-info">
                        <div class="companies-card-row"><span>Industry:</span> <strong style="color: var(--text-heading);">${escapeHtml(c.industry || '—')}</strong></div>
                        <div class="companies-card-row"><span>Location:</span> <span>${escapeHtml(c.location || '—')}</span></div>
                        <div class="companies-card-row"><span>Primary Contact:</span> <strong>${escapeHtml(c.primaryContactName || 'None')}</strong></div>
                        <div class="companies-card-row"><span>Open Deals:</span> <strong>${c.openDealsCount || 0} ($${(c.openDealsValue || 0).toLocaleString()})</strong></div>
                    </div>
                    <div class="companies-card-footer">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <div class="avatar avatar-xs" style="background-color: ${c.ownerColor || '#0284C7'};">${escapeHtml(c.ownerInitials || 'UN')}</div>
                            <span style="font-size: 11px; color: var(--text-muted);">${escapeHtml(c.owner)}</span>
                        </div>
                        <button type="button" class="btn btn-ghost btn-xs company-view-trigger" data-company-id="${c.id}" aria-label="View company details">View Details &rarr;</button>
                    </div>
                </div>
            `;
        }).join("");
    }

    function renderPagination() {
        const start = totalRecords === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
        const end = Math.min(currentPage * rowsPerPage, totalRecords);

        const rangeEl = document.getElementById("pagingRange");
        if (rangeEl) rangeEl.textContent = `${start}–${end}`;

        const totalEl = document.getElementById("pagingTotal");
        if (totalEl) totalEl.textContent = totalRecords.toLocaleString();

        const controls = document.getElementById("paginationControls");
        if (!controls) return;

        const pages = getPaginationPages(currentPage, totalPages);
        let html = `
            <button class="pagination-btn" id="prevPageBtn" ${currentPage <= 1 ? 'disabled' : ''} onclick="window.companiesApp.goToPage(${currentPage - 1})">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
        `;

        pages.forEach(p => {
            if (p === '...') {
                html += `<span style="padding: 0 4px; color: var(--text-muted);">...</span>`;
            } else {
                const isActive = (p === currentPage);
                html += `<button type="button" class="pagination-btn ${isActive ? 'active' : ''}" onclick="window.companiesApp.goToPage(${p})">${p}</button>`;
            }
        });

        html += `
            <button class="pagination-btn" id="nextPageBtn" ${currentPage >= totalPages ? 'disabled' : ''} onclick="window.companiesApp.goToPage(${currentPage + 1})">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        `;

        controls.innerHTML = html;
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

    // --- Form Submit Handlers ---
    function submitAddOrEditCompany(e) {
        e.preventDefault();
        const editId = document.getElementById("editCompanyId").value;
        const name = document.getElementById("addCompanyName").value.trim();
        const domain = document.getElementById("addCompanyDomain").value.trim();
        const industry = document.getElementById("addCompanyIndustry").value;
        const size = document.getElementById("addCompanySize").value;
        const revenue = document.getElementById("addCompanyRevenue").value.trim();
        const location = document.getElementById("addCompanyLocation").value.trim();
        const foundedYear = document.getElementById("addCompanyFoundedYear") ? document.getElementById("addCompanyFoundedYear").value.trim() : "";
        const linkedin = document.getElementById("addCompanyLinkedin") ? document.getElementById("addCompanyLinkedin").value.trim() : "";
        const taxReg = document.getElementById("addCompanyTaxReg") ? document.getElementById("addCompanyTaxReg").value.trim() : "";
        const selectedContactId = document.getElementById("addCompanySelectedContactId") ? document.getElementById("addCompanySelectedContactId").value.trim() : "";
        const contactPhone = document.getElementById("addCompanyContactPhone") ? document.getElementById("addCompanyContactPhone").value.trim() : "";
        const contactName = document.getElementById("addCompanyContactName") ? document.getElementById("addCompanyContactName").value.trim() : "";
        const contactEmail = document.getElementById("addCompanyContactEmail") ? document.getElementById("addCompanyContactEmail").value.trim() : "";
        const rel = document.getElementById("addCompanyRel").value;
        const ownerSelect = document.getElementById("addCompanyOwner");
        const ownerName = ownerSelect ? ownerSelect.value : "";
        const ownerId = (ownerSelect && ownerSelect.selectedOptions[0]) ? ownerSelect.selectedOptions[0].getAttribute("data-id") : null;

        if (!name) {
            showCompaniesToast("Company name is required.");
            return;
        }

        const payload = {
            name: name,
            domain: domain,
            industry: industry,
            company_size: size,
            annual_revenue: revenue,
            location: location,
            founded_year: foundedYear ? parseInt(foundedYear, 10) : null,
            linkedin: linkedin,
            tax_registration_number: taxReg,
            relationship: rel,
            owner_id: ownerId ? parseInt(ownerId, 10) : null
        };

        if (selectedContactId) {
            payload.primary_contact_id = parseInt(selectedContactId, 10);
        } else if (contactName) {
            payload.new_contact_name = contactName;
            payload.new_contact_phone = contactPhone;
            payload.new_contact_email = contactEmail;
        } else if (editId) {
            payload.primary_contact_id = null;
        }

        const actionName = editId ? 'update' : 'create';
        if (editId) payload.id = parseInt(editId, 10);

        fetch('api/companies.php?action=' + actionName, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                showCompaniesToast(res.message || 'Error saving company.');
                return;
            }

            showCompaniesToast(res.message || (editId ? 'Company updated.' : 'Company created.'));
            window.companiesApp.closeAddDrawer();

            // Refresh table
            fetchCompanies(() => {
                if (editId && activeCompanyForDetails && activeCompanyForDetails.id === parseInt(editId, 10)) {
                    window.companiesApp.openDetails(parseInt(editId, 10));
                }
            });
        })
        .catch(err => {
            console.error('Save company error:', err);
            showCompaniesToast('Network error while saving company.');
        });
    }

    function submitAddTaskModal(e) {
        e.preventDefault();
        if (!activeCompanyForDetails) return;

        const companyId = activeCompanyForDetails.id;
        const title = document.getElementById("compTaskTitleInput").value.trim();
        const type = document.getElementById("compTaskTypeSelect").value;
        const priority = document.getElementById("compTaskPrioritySelect").value;
        const dueDate = document.getElementById("compTaskDueDateInput").value.trim();
        const ownerSelect = document.getElementById("compTaskOwnerSelect");
        const ownerId = (ownerSelect && ownerSelect.selectedOptions[0]) ? ownerSelect.selectedOptions[0].getAttribute("data-id") : null;
        const notes = document.getElementById("compTaskNotesInput").value.trim();

        if (!title) {
            showCompaniesToast("Task title is required.");
            return;
        }

        const payload = {
            company_id: companyId,
            title: title,
            type: type,
            priority: priority,
            due_date: dueDate,
            assigned_to: ownerId ? parseInt(ownerId, 10) : null,
            notes: notes
        };

        fetch('api/companies.php?action=add_task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                showCompaniesToast(res.message || 'Error adding task.');
                return;
            }

            showCompaniesToast("Task added successfully.");
            window.companiesApp.closeAddTaskModal();
            window.companiesApp.switchDetailsTab("tasks");
        })
        .catch(err => {
            console.error('Add task error:', err);
            showCompaniesToast('Network error while adding task.');
        });
    }

    // --- Dynamic Drawer Rendering ---
    function renderTabPanelContent(tabName, container, company, drawerData) {
        if (!drawerData) {
            container.innerHTML = '<div style="padding: 24px; text-align: center; color: var(--text-muted);">Loading...</div>';
            return;
        }

        const c = drawerData.company || company;
        const cId = c.id;

        if (tabName === "overview") {
            const perf = drawerData.performance || {};
            const cfList = drawerData.custom_fields || [];

            container.innerHTML = `
                <div class="company-view-info-card">
                    <div class="company-view-info-title">Company Information</div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Legal Name</span> <span class="contacts-info-val">${escapeHtml(c.legal_name || c.name)}</span></div>
                    <div class="company-view-info-row">
                        <span class="contacts-info-label">Website</span>
                        <div>
                            <a href="${c.domain ? (c.domain.startsWith('http') ? c.domain : 'https://' + c.domain) : '#'}" target="_blank" rel="noopener" style="color: var(--primary); font-weight: 500;">${escapeHtml(c.domain || '—')}</a>
                            ${c.domain ? `
                            <button type="button" class="company-view-copy-btn" title="Copy Website" onclick="window.companiesApp.copyToClipboard('${c.domain}')">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                            </button>` : ''}
                        </div>
                    </div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Industry</span> <span class="contacts-info-val">${escapeHtml(c.industry || '—')}</span></div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Company Size</span> <span class="contacts-info-val">${escapeHtml(c.company_size || '—')}</span></div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Annual Revenue</span> <span class="contacts-info-val">${c.annual_revenue ? '$' + Number(c.annual_revenue).toLocaleString() : '—'}</span></div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Location</span> <span class="contacts-info-val">${escapeHtml(c.location || '—')}</span></div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Founded Year</span> <span class="contacts-info-val">${c.founded_year || '—'}</span></div>
                    <div class="company-view-info-row"><span class="contacts-info-label">LinkedIn</span> <span class="contacts-info-val" style="color: var(--primary);">${c.linkedin ? `<a href="${c.linkedin.startsWith('http') ? c.linkedin : 'https://' + c.linkedin}" target="_blank" rel="noopener">${escapeHtml(c.linkedin)}</a>` : '—'}</span></div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Tax Registration Number</span> <span class="contacts-info-val">${escapeHtml(c.tax_registration_number || '—')}</span></div>
                    ${cfList.filter(cf => cf.field_key !== 'tax_registration_number' && (cf.field_label || '').toLowerCase() !== 'tax registration number').map(cf => `
                        <div class="company-view-info-row"><span class="contacts-info-label">${escapeHtml(cf.field_label)}</span> <span class="contacts-info-val">${escapeHtml(cf.field_value || '—')}</span></div>
                    `).join('')}
                </div>

                <div class="company-view-info-card">
                    <div class="company-view-info-title">CRM & Account Details</div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Relationship</span> <span class="contacts-info-val">${escapeHtml(c.relationship || '—')}</span></div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Account Owner</span> <span class="contacts-info-val">${escapeHtml(c.owner_name || 'Unassigned')}</span></div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Primary Contact</span> <span class="contacts-info-val">${escapeHtml(c.primary_contact_name || 'None')}</span></div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Source</span> <span class="contacts-info-val">${escapeHtml(c.source || 'Manual Entry')}</span></div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Created Date</span> <span class="contacts-info-val">${c.created_at ? new Date(c.created_at).toLocaleDateString() : '—'}</span></div>
                </div>

                <div class="company-view-info-card">
                    <div class="company-view-info-title">Account Performance Summary</div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Total Contacts</span> <span class="contacts-info-val">${perf.totalContacts || 0} contacts</span></div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Open Deals</span> <span class="contacts-info-val">${perf.openDeals || 0} deals</span></div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Pipeline Value</span> <span class="contacts-info-val" style="color: var(--primary); font-weight: 600;">$${(perf.pipelineValue || 0).toLocaleString()}</span></div>
                    <div class="company-view-info-row"><span class="contacts-info-label">Won Value / LTV</span> <span class="contacts-info-val" style="color: #12B76A; font-weight: 600;">$${(perf.wonValue || 0).toLocaleString()}</span></div>
                </div>
            `;
        } else if (tabName === "contacts") {
            const contacts = drawerData.contacts || [];

            container.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span style="font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Associated Contacts (${contacts.length})</span>
                    <button type="button" class="btn btn-primary btn-xs" data-company-action="add-contact" data-company-id="${cId}">+ Add Contact</button>
                </div>

                <div>
                    ${contacts.length > 0 ? contacts.map(contact => {
                        const isPrimary = c.primary_contact_id && String(contact.id) === String(c.primary_contact_id);
                        return `
                        <div class="company-contact-item">
                            <div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0;">
                                <div class="avatar avatar-sm" style="background-color: ${company_color(contact.name)}; flex-shrink: 0;">${company_initials(contact.name)}</div>
                                <div style="min-width: 0;">
                                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                        <span style="font-weight: 600; color: var(--text-heading); font-size: 12.5px;">${escapeHtml(contact.name)}</span>
                                        ${isPrimary ? `<span class="badge-primary-contact" title="Primary Contact" style="font-size: 11px; padding: 1px 6px; border-radius: 10px; background-color: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-weight: 600; display: inline-flex; align-items: center; gap: 3px;"><span style="font-size: 9.5px; color: #D97706;">★</span> Primary</span>` : ''}
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(contact.job_title || '')} • <span style="color: var(--primary);">${escapeHtml(contact.relationship || 'Customer')}</span></div>
                                    <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 2px;">${escapeHtml(contact.email || '')} • ${escapeHtml(contact.phone || '')}</div>
                                </div>
                            </div>
                            <div class="company-contact-actions">
                                <button type="button" class="contacts-comm-btn call" title="Call Contact" data-company-action="contact-call" data-contact-id="${contact.id}" data-company-id="${cId}">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                                </button>
                                <button type="button" class="contacts-comm-btn wa" title="WhatsApp Contact" data-company-action="contact-whatsapp" data-contact-id="${contact.id}" data-company-id="${cId}">
                                    <svg width="13" height="13" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                </button>
                                <button type="button" class="contacts-comm-btn email" title="Email Contact" data-company-action="contact-email" data-contact-id="${contact.id}" data-company-id="${cId}">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </button>
                            </div>
                        </div>
                    `; }).join("") : `
                        <div class="company-view-empty-state" style="padding: 24px 16px; text-align: center;">
                            <div style="font-weight: 600; font-size: 13px; color: var(--text-heading); margin-bottom: 4px;">No Contacts</div>
                            <p style="font-size: 12px; color: var(--text-muted); margin: 0;">No contacts associated with this company yet.</p>
                        </div>
                    `}
                </div>
            `;
        } else if (tabName === "deals") {
            const dealsList = drawerData.deals || [];
            const openDeals = dealsList.filter(d => d.status === 'open');
            const wonDeals = dealsList.filter(d => d.status === 'won');
            const pipelineValue = openDeals.reduce((sum, d) => sum + (parseFloat(d.value) || 0), 0);
            const wonValue = wonDeals.reduce((sum, d) => sum + (parseFloat(d.value) || 0), 0);

            container.innerHTML = `
                <div class="company-deals-summary">
                    <div style="flex: 1;">
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">OPEN DEALS</div>
                        <div style="font-size: 16px; font-weight: 700; color: var(--text-heading); margin-top: 2px;">${openDeals.length}</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">PIPELINE VALUE</div>
                        <div style="font-size: 16px; font-weight: 700; color: var(--primary); margin-top: 2px;">$${pipelineValue.toLocaleString()}</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">WON VALUE</div>
                        <div style="font-size: 16px; font-weight: 700; color: #12B76A; margin-top: 2px;">$${wonValue.toLocaleString()}</div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span style="font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Company Opportunities</span>
                    <button type="button" class="btn btn-primary btn-xs" data-company-action="create-deal" data-company-id="${cId}">+ Create Deal</button>
                </div>

                <div>
                    ${dealsList.length > 0 ? dealsList.map(d => {
                        const prob = d.probability ? parseInt(d.probability, 10) : 50;
                        let stageColor = "#2563EB";
                        if (d.stage === "Proposal") stageColor = "#7C3AED";
                        if (d.status === "won" || d.stage === "Won") stageColor = "#12B76A";
                        if (d.status === "lost" || d.stage === "Lost") stageColor = "#DC2626";

                        return `
                            <div class="company-deal-card">
                                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;">
                                    <div>
                                        <div style="font-size: 13.5px; font-weight: 600; color: var(--text-heading);">${escapeHtml(d.name)}</div>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">${escapeHtml(c.name)}</div>
                                    </div>
                                    <span class="companies-badge" style="background-color: ${stageColor}18; color: ${stageColor};">${escapeHtml(d.stage)}</span>
                                </div>
                                <div style="margin-top: 10px; display: flex; align-items: center; justify-content: space-between; font-size: 12px;">
                                    <strong style="font-size: 14px; color: var(--text-heading);">$${parseFloat(d.value || 0).toLocaleString()}</strong>
                                    <span style="font-size: 11px; color: var(--text-muted);">${prob}% probability</span>
                                </div>
                                <div class="company-deal-progress">
                                    <div class="company-deal-bar" style="width: ${prob}%; background-color: ${stageColor};"></div>
                                </div>
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px; color: var(--text-muted);">
                                    <span>Close: ${d.close_date || 'Not set'}</span>
                                    <button type="button" class="btn btn-ghost btn-xs" style="color: var(--primary);" data-company-action="view-deal" data-deal-id="${d.id}" data-company-id="${cId}">View Deal &rarr;</button>
                                </div>
                            </div>
                        `;
                    }).join("") : `
                        <div class="company-view-empty-state" style="padding: 24px 16px; text-align: center;">
                            <div style="font-weight: 600; font-size: 13px; color: var(--text-heading); margin-bottom: 4px;">No Deals</div>
                            <p style="font-size: 12px; color: var(--text-muted); margin: 0;">No deals recorded for this company yet.</p>
                        </div>
                    `}
                </div>
            `;
        } else if (tabName === "activity") {
            const activities = drawerData.activities || [];

            container.innerHTML = `
                <div class="company-view-activity-header">
                    <span style="font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Company Activity Timeline</span>
                    <button type="button" class="btn btn-primary btn-xs" data-company-action="log-activity" data-company-id="${cId}">+ Log Activity</button>
                </div>
                <div>
                    ${activities.length > 0 ? renderTimelineGroups(activities) : `
                        <div class="company-view-empty-state" style="padding: 24px 16px; text-align: center;">
                            <div style="font-weight: 600; font-size: 13px; color: var(--text-heading); margin-bottom: 4px;">No Activities</div>
                            <p style="font-size: 12px; color: var(--text-muted); margin: 0;">No activity logged yet for this company.</p>
                        </div>
                    `}
                </div>
            `;
        } else if (tabName === "tasks") {
            const tasksList = drawerData.tasks || [];
            tasksList.forEach(t => {
                t.done = (t.status === 'completed');
                t.section = determineTaskSection(t.due_date, t.done);
            });

            const overdue = tasksList.filter(t => !t.done && t.section === "Overdue");
            const today = tasksList.filter(t => !t.done && t.section === "Today");
            const upcoming = tasksList.filter(t => !t.done && t.section === "Upcoming");
            const completed = tasksList.filter(t => t.done);
            const hasTasks = tasksList.length > 0;

            container.innerHTML = `
                <div class="company-view-tasks-toolbar">
                    <span style="font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Account Tasks</span>
                    <button type="button" class="btn btn-primary btn-xs" onclick="window.companiesApp.openAddTaskModal()">+ Add Task</button>
                </div>

                <div id="compTasksListContainer">
                    ${hasTasks ? `
                        ${renderTaskSection("Overdue Tasks", overdue, "#B42318", cId)}
                        ${renderTaskSection("Today", today, "#2563EB", cId)}
                        ${renderTaskSection("Upcoming", upcoming, "#7C3AED", cId)}
                        ${renderTaskSection("Completed Tasks", completed, "#027A48", cId)}
                    ` : `
                        <div class="company-view-empty-state" style="padding: 24px 16px; text-align: center;">
                            <div style="font-weight: 600; font-size: 13px; color: var(--text-heading); margin-bottom: 4px;">No Tasks</div>
                            <p style="font-size: 12px; color: var(--text-muted); margin: 0;">No tasks created for this company yet. Click "+ Add Task" to create one.</p>
                        </div>
                    `}
                </div>
            `;
        } else if (tabName === "notes") {
            const notes = drawerData.notes || [];

            container.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px;">
                    <div class="input-field-container" style="flex: 1;">
                        <input type="search" class="input-control input-sm has-icon" id="compNotesSearchInput" placeholder="Search notes..." value="${escapeHtml(compNotesSearchQuery)}" oninput="window.companiesApp.filterCompanyNotes(this.value)">
                    </div>
                    <button type="button" class="btn btn-primary btn-xs" onclick="window.companiesApp.openAddNoteModal()">+ Add Note</button>
                </div>

                <div id="compNotesContainer">
                    ${renderNotesCards(notes, cId)}
                </div>
            `;
        }
    }

    function determineTaskSection(dueDateStr, isDone) {
        if (isDone) return "Completed";
        if (!dueDateStr) return "Upcoming";
        const d = new Date(dueDateStr);
        if (isNaN(d.getTime())) return "Upcoming";

        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const taskDate = new Date(d.getFullYear(), d.getMonth(), d.getDate());

        if (taskDate < today) return "Overdue";
        if (taskDate.getTime() === today.getTime()) return "Today";
        return "Upcoming";
    }

    function renderTaskSection(title, list, color, companyId) {
        if (!list || list.length === 0) return "";
        return `
            <div class="company-task-section">
                <div class="company-task-section-title" style="color: ${color};">
                    ${escapeHtml(title)} (${list.length})
                </div>
                ${list.map(t => `
                    <div class="company-task-item ${t.done ? 'completed' : ''}">
                        <input type="checkbox" ${t.done ? 'checked' : ''} style="accent-color: var(--primary); width: 16px; height: 16px; cursor: pointer; flex-shrink: 0;" onchange="window.companiesApp.toggleTaskDone('${companyId}', '${t.id}', this.checked)">
                        <div style="flex: 1; min-width: 0;">
                            <div class="company-task-title ${t.done ? 'done' : ''}">${escapeHtml(t.title)}</div>
                            <div class="company-task-meta">
                                <span>${t.due_date ? new Date(t.due_date).toLocaleDateString() : 'No due date'}</span>
                                <span>•</span>
                                <span class="priority-badge ${t.priority || 'medium'}">${escapeHtml(t.priority || 'Medium')}</span>
                                ${t.assigned_to_name ? `<span>• ${escapeHtml(t.assigned_to_name)}</span>` : ''}
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 4px;">
                            <button type="button" class="btn btn-ghost btn-xs" style="color: var(--primary); font-weight: 500; padding: 2px 6px;" onclick="window.companiesApp.openEditTaskModal('${companyId}', '${t.id}')">Edit</button>
                            <button type="button" class="btn btn-ghost btn-xs" style="color: #F04438; font-weight: 500; padding: 2px 6px;" onclick="window.companiesApp.openDeleteTaskModal('${companyId}', '${t.id}')">Delete</button>
                        </div>
                    </div>
                `).join("")}
            </div>
        `;
    }

    function renderTimelineGroups(activities) {
        if (!activities || activities.length === 0) {
            return `
                <div class="company-view-empty-state" style="padding: 24px 16px; text-align: center;">
                    <div style="font-weight: 600; font-size: 13px; color: var(--text-heading); margin-bottom: 4px;">No Activities</div>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 0;">No activity logged yet for this company.</p>
                </div>
            `;
        }

        const typeMap = {
            "call":            { icon: "📞", color: "#12B76A" },
            "email":           { icon: "✉️", color: "#2563EB" },
            "whatsapp":        { icon: "💬", color: "#25D366" },
            "meeting":         { icon: "📅", color: "#7C3AED" },
            "note":            { icon: "📌", color: "#7C3AED" },
            "company_created": { icon: "🏢", color: "#0284C7" },
            "deal_created":    { icon: "🚀", color: "#F79009" },
            "task_created":    { icon: "✓", color: "#059669" },
            "contact_added":   { icon: "👤", color: "#7C3AED" },
            "other":           { icon: "⭐", color: "#F79009" }
        };

        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

        function getActivityGroup(dateStr) {
            if (!dateStr) return "EARLIER";
            const safeStr = String(dateStr).replace(' ', 'T');
            const d = new Date(safeStr);
            if (isNaN(d.getTime())) return "EARLIER";

            const actDate = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            const diffDays = Math.floor((today.getTime() - actDate.getTime()) / (1000 * 60 * 60 * 24));

            if (diffDays <= 0) return "TODAY";
            if (diffDays === 1) return "YESTERDAY";
            if (diffDays <= 7) return "EARLIER THIS WEEK";

            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).toUpperCase();
        }

        const groups = {};
        activities.forEach(a => {
            const groupName = getActivityGroup(a.created_at);
            if (!groups[groupName]) groups[groupName] = [];
            groups[groupName].push(a);
        });

        let html = "";
        for (const [groupName, items] of Object.entries(groups)) {
            html += `
                <div class="company-view-timeline-group">
                    <div class="company-view-group-label">${escapeHtml(groupName)}</div>
                    <div class="company-view-timeline">
                        ${items.map(item => {
                            const rawType = (item.activity_type || 'other').toLowerCase();
                            const meta = typeMap[rawType] || typeMap['other'];
                            const status = item.status || item.outcome || (item.activity_type && !['call', 'meeting', 'email', 'note', 'other'].includes(rawType) ? item.activity_type : '');
                            const timeStr = item.created_at ? company_time_ago(item.created_at) : '';

                            return `
                                <div class="company-view-timeline-item">
                                    <div class="company-view-timeline-icon" style="background-color: ${meta.color}18; color: ${meta.color};">${meta.icon}</div>
                                    <div class="company-view-timeline-card">
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                            <div style="font-weight: 600; color: var(--text-heading); font-size: 12.5px;">${escapeHtml(item.title)}</div>
                                            <span style="font-size: 10.5px; color: var(--text-muted);">${escapeHtml(timeStr)}</span>
                                        </div>
                                        ${item.description ? `<p style="margin: 2px 0 4px; color: var(--text-secondary); font-size: 11.5px; line-height: 1.4;">${escapeHtml(item.description)}</p>` : ''}
                                        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 10.5px; color: var(--text-muted); margin-top: 4px;">
                                            <span>Logged by: <strong style="color: var(--text-body);">${escapeHtml(item.user_name || 'Team Member')}</strong></span>
                                            ${status ? `<span class="contacts-badge" style="background-color: ${meta.color}18; color: ${meta.color}; font-size: 9.5px; padding: 1px 6px;">${escapeHtml(status)}</span>` : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join("")}
                    </div>
                </div>
            `;
        }
        return html;
    }

    function renderNotesCards(notes, companyId) {
        if (!notes || notes.length === 0) {
            return `
                <div class="company-view-empty-state" style="padding: 24px 16px; text-align: center;">
                    <div style="font-weight: 600; font-size: 13px; color: var(--text-heading); margin-bottom: 4px;">No Notes</div>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 0;">No notes found for this company. Click "+ Add Note" to create one.</p>
                </div>
            `;
        }

        const filtered = compNotesSearchQuery ? notes.filter(n => 
            (n.title && n.title.toLowerCase().includes(compNotesSearchQuery.toLowerCase())) ||
            (n.content && n.content.toLowerCase().includes(compNotesSearchQuery.toLowerCase()))
        ) : notes;

        return filtered.map(n => `
            <div class="company-note-card ${n.is_pinned ? 'pinned' : ''}">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;">
                    <div style="font-size: 13px; font-weight: 600; color: var(--text-heading);">${escapeHtml(n.title || 'Note')}</div>
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <button type="button" class="btn btn-ghost btn-xs" style="padding: 2px 6px; color: ${n.is_pinned ? 'var(--primary)' : 'var(--text-muted)'};" title="${n.is_pinned ? 'Unpin' : 'Pin'}" onclick="window.companiesApp.togglePinNote('${companyId}', '${n.id}')">
                            <svg width="12" height="12" fill="${n.is_pinned ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        </button>
                        <button type="button" class="btn btn-ghost btn-xs" style="padding: 2px 6px;" title="Edit" onclick="window.companiesApp.openEditNoteModal('${companyId}', '${n.id}')">Edit</button>
                        <button type="button" class="btn btn-ghost btn-xs" style="padding: 2px 6px; color: #F04438;" title="Delete" onclick="window.companiesApp.openDeleteNoteModal('${companyId}', '${n.id}')">Delete</button>
                    </div>
                </div>
                <p style="font-size: 12px; color: var(--text-secondary); margin: 6px 0 8px; white-space: pre-wrap;">${escapeHtml(n.content)}</p>
                <div style="font-size: 10.5px; color: var(--text-muted); display: flex; justify-content: space-between;">
                    <span>${escapeHtml(n.author_name || 'Team Member')}</span>
                    <span>${n.created_at ? new Date(n.created_at).toLocaleDateString() : ''}</span>
                </div>
            </div>
        `).join("");
    }

    // --- Public Companies App Object ---
    window.companiesApp = {
        resetAccountView: function() {
            saveAccountViewState("all");
            const viewsSelect = document.getElementById("companiesViewsSelect");
            if (viewsSelect) viewsSelect.value = "all";
            currentPage = 1;
            fetchCompanies();
        },

        filterByCard: function(filterType, elem) {
            cardFilter = filterType || "All";
            document.querySelectorAll(".company-kpi-card").forEach(c => {
                const attr = c.getAttribute("data-filter");
                c.classList.toggle("active", attr === cardFilter);
            });
            currentPage = 1;
            fetchCompanies();
        },

        filterByTab: function(rel, elem) {
            this.filterByCard(rel, elem);
        },

        setViewMode: function(mode) {
            viewMode = mode;
            document.getElementById("btnViewList").classList.toggle("active", mode === "list");
            document.getElementById("btnViewGrid").classList.toggle("active", mode === "grid");
            renderCompanies();
        },

        setRowsPerPage: function(val) {
            rowsPerPage = parseInt(val, 10) || 8;
            currentPage = 1;
            fetchCompanies();
        },

        toggleRowSelection: function(id, isChecked) {
            if (isChecked) {
                selectedCompanyIds.add(id);
            } else {
                selectedCompanyIds.delete(id);
            }
            updateBulkToolbarState();
        },

        executeBulkAction: function(actionName) {
            const count = selectedCompanyIds.size;
            if (count === 0) return;

            if (actionName === "delete") {
                if (!confirm(`Are you sure you want to delete ${count} selected companies?`)) return;

                fetch('api/companies.php?action=bulk_action', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        bulk_action: 'delete',
                        company_ids: Array.from(selectedCompanyIds)
                    })
                })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        showCompaniesToast(res.message || 'Error executing bulk delete.');
                        return;
                    }
                    showCompaniesToast(res.message || `${count} companies deleted.`);
                    selectedCompanyIds.clear();
                    fetchCompanies();
                });
            } else if (actionName === "archive") {
                fetch('api/companies.php?action=bulk_action', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        bulk_action: 'archive',
                        company_ids: Array.from(selectedCompanyIds)
                    })
                })
                .then(r => r.json())
                .then(res => {
                    showCompaniesToast(res.message || `${count} companies archived.`);
                    selectedCompanyIds.clear();
                    fetchCompanies();
                });
            }
        },

        goToPage: function(p) {
            currentPage = p;
            fetchCompanies();
        },

        changePage: function(delta) {
            currentPage += delta;
            fetchCompanies();
        },

        openDetails: function(id, triggerElement) {
            if (triggerElement) {
                lastFocusedElement = triggerElement;
            } else {
                lastFocusedElement = document.activeElement;
            }

            const drawer = document.getElementById("companyDetailsDrawer");
            if (drawer) {
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
            }
            document.body.style.overflow = "hidden";

            // Fetch live drawer data from API
            fetch('api/companies.php?action=drawer_data&id=' + id)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        showCompaniesToast(res.message || 'Error loading company details.');
                        return;
                    }

                    activeDrawerData = res.data;
                    const c = res.data.company;
                    activeCompanyForDetails = c;

                    const avatar = document.getElementById("compAvatar");
                    const name = document.getElementById("compName");
                    const breadcrumbName = document.getElementById("compBreadcrumbName");
                    const indSize = document.getElementById("compIndustrySize");
                    const relBadge = document.getElementById("compRelBadge");

                    if (avatar) {
                        avatar.textContent = company_initials(c.name);
                        avatar.style.backgroundColor = company_color(c.name);
                    }
                    if (name) name.textContent = c.name;
                    if (indSize) {
                        const parts = [c.industry, c.company_size].filter(Boolean);
                        indSize.textContent = parts.length > 0 ? parts.join(' • ') : '—';
                    }
                    if (relBadge) {
                        if (c.relationship) {
                            relBadge.textContent = c.relationship;
                            relBadge.className = `companies-badge ${c.relationship.toLowerCase()}`;
                            relBadge.style.display = '';
                        } else {
                            relBadge.textContent = '';
                            relBadge.style.display = 'none';
                        }
                    }

                    const ownerEl = document.getElementById("compOwner");
                    const locEl = document.getElementById("compLocation");
                    if (ownerEl) ownerEl.textContent = c.owner_name || 'Unassigned';
                    if (locEl) locEl.textContent = c.location || '—';

                    const statusBtn = document.getElementById("compStatusToggleBtn");
                    if (statusBtn) {
                        const isActive = Number(c.is_active) === 1;
                        statusBtn.textContent = isActive ? "Mark as Inactive" : "Mark as Active";
                    }

                    window.companiesApp.switchDetailsTab(activeTabName || "overview");
                })
                .catch(err => {
                    console.error('Drawer data error:', err);
                    showCompaniesToast('Network error loading drawer.');
                });
        },

        closeDetails: function() {
            const drawer = document.getElementById("companyDetailsDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
            }
            document.body.style.overflow = "";
            window.companiesApp.closeMoreDropdown();
            restoreKeyboardFocus();
        },

        switchDetailsTab: function(tabName) {
            activeTabName = tabName.toLowerCase();

            const tabs = document.querySelectorAll(".company-view-tabs [role='tab']");
            tabs.forEach(t => {
                const isSelected = t.id === `tab-comp-${activeTabName}`;
                t.classList.toggle("active", isSelected);
                t.setAttribute("aria-selected", isSelected ? "true" : "false");
                t.setAttribute("tabindex", isSelected ? "0" : "-1");
            });

            const container = document.getElementById("compTabContent");
            if (!container || !activeCompanyForDetails) return;

            const drawerBody = document.querySelector(".company-view-body");
            if (drawerBody) drawerBody.scrollTop = 0;

            renderTabPanelContent(activeTabName, container, activeCompanyForDetails, activeDrawerData);
        },

        toggleMoreDropdown: function() {
            const menu = document.getElementById("compMoreMenu");
            if (menu) {
                if (activeCompanyForDetails) {
                    const statusBtn = document.getElementById("compStatusToggleBtn");
                    if (statusBtn) {
                        const isActive = Number(activeCompanyForDetails.is_active) === 1;
                        statusBtn.textContent = isActive ? "Mark as Inactive" : "Mark as Active";
                    }
                }
                menu.classList.toggle("show");
            }
        },

        closeMoreDropdown: function() {
            const menu = document.getElementById("compMoreMenu");
            if (menu) menu.classList.remove("show");
        },

        openAddDrawer: function() {
            lastFocusedElement = document.activeElement;
            const form = document.getElementById("addCompanyForm");
            if (form) form.reset();
            document.getElementById("editCompanyId").value = "";
            document.getElementById("addCompanyModalTitle").textContent = "Add New Company";
            document.getElementById("btnSaveCompanySubmit").textContent = "Save Company";

            const phoneInput = document.getElementById("addCompanyContactPhone");
            const nameInput = document.getElementById("addCompanyContactName");
            const selIdInput = document.getElementById("addCompanySelectedContactId");
            const emailInput = document.getElementById("addCompanyContactEmail");
            const helper = document.getElementById("addCompanyContactHelper");
            const emailGrp = document.getElementById("addCompanyNewEmailGroup");
            const drop = document.getElementById("addCompanyPhoneDropdown");

            if (phoneInput) phoneInput.value = "";
            if (nameInput) {
                nameInput.value = "";
                nameInput.readOnly = false;
            }
            if (selIdInput) selIdInput.value = "";
            if (emailInput) emailInput.value = "";
            if (document.getElementById("addCompanyFoundedYear")) document.getElementById("addCompanyFoundedYear").value = "";
            if (document.getElementById("addCompanyLinkedin")) document.getElementById("addCompanyLinkedin").value = "";
            if (document.getElementById("addCompanyTaxReg")) document.getElementById("addCompanyTaxReg").value = "";
            if (helper) helper.style.display = "none";
            if (emailGrp) emailGrp.style.display = "none";
            if (drop) {
                drop.innerHTML = "";
                drop.style.display = "none";
            }

            const drawer = document.getElementById("companiesAddDrawer");
            if (drawer) drawer.classList.add("show");
        },

        openEditFromDetails: function() {
            if (!activeCompanyForDetails) return;
            const c = activeCompanyForDetails;

            window.companiesApp.closeMoreDropdown();

            document.getElementById("editCompanyId").value = c.id;
            document.getElementById("addCompanyModalTitle").textContent = "Edit Company";
            document.getElementById("btnSaveCompanySubmit").textContent = "Update Company";

            document.getElementById("addCompanyName").value = c.name || "";
            document.getElementById("addCompanyDomain").value = c.domain || "";
            document.getElementById("addCompanyIndustry").value = c.industry || "";
            document.getElementById("addCompanySize").value = c.company_size || "";
            document.getElementById("addCompanyRevenue").value = c.annual_revenue || "";
            if (document.getElementById("addCompanyFoundedYear")) document.getElementById("addCompanyFoundedYear").value = c.founded_year || "";
            if (document.getElementById("addCompanyLinkedin")) document.getElementById("addCompanyLinkedin").value = c.linkedin || "";
            if (document.getElementById("addCompanyTaxReg")) document.getElementById("addCompanyTaxReg").value = c.tax_registration_number || "";
            document.getElementById("addCompanyLocation").value = c.location || "";
            document.getElementById("addCompanyRel").value = c.relationship || "";

            const ownerSelect = document.getElementById("addCompanyOwner");
            if (ownerSelect && c.owner_name) {
                ownerSelect.value = c.owner_name;
            }

            const phoneInput = document.getElementById("addCompanyContactPhone");
            const nameInput = document.getElementById("addCompanyContactName");
            const selIdInput = document.getElementById("addCompanySelectedContactId");
            const emailInput = document.getElementById("addCompanyContactEmail");
            const helper = document.getElementById("addCompanyContactHelper");
            const emailGrp = document.getElementById("addCompanyNewEmailGroup");
            const drop = document.getElementById("addCompanyPhoneDropdown");

            const primaryId = c.primary_contact_id || "";
            const primaryName = c.primary_contact_name || "";
            const primaryPhone = c.primary_contact_phone || "";
            const primaryEmail = c.primary_contact_email || "";

            if (selIdInput) selIdInput.value = primaryId;
            if (nameInput) {
                nameInput.value = primaryName;
                nameInput.readOnly = Boolean(primaryId);
            }
            if (phoneInput) phoneInput.value = primaryPhone;
            if (emailInput) emailInput.value = primaryEmail;
            if (helper) helper.style.display = "none";
            if (emailGrp) emailGrp.style.display = "none";
            if (drop) {
                drop.innerHTML = "";
                drop.style.display = "none";
            }

            const drawer = document.getElementById("companiesAddDrawer");
            if (drawer) drawer.classList.add("show");
        },

        closeAddDrawer: function() {
            const drawer = document.getElementById("companiesAddDrawer");
            if (drawer) drawer.classList.remove("show");
            const drop = document.getElementById("addCompanyPhoneDropdown");
            if (drop) { drop.style.display = "none"; drop.innerHTML = ""; }
            const helper = document.getElementById("addCompanyContactHelper");
            if (helper) helper.style.display = "none";
            const emailGrp = document.getElementById("addCompanyNewEmailGroup");
            if (emailGrp) emailGrp.style.display = "none";
            const nameInput = document.getElementById("addCompanyContactName");
            if (nameInput) nameInput.readOnly = false;
            restoreKeyboardFocus();
        },

        toggleFilterPopover: function(e) {
            if (e && e.stopPropagation) e.stopPropagation();
            const popover = document.getElementById("companiesFilterPopover");
            if (popover) popover.classList.toggle("show");
        },

        closeFilterPopover: function() {
            const popover = document.getElementById("companiesFilterPopover");
            if (popover) popover.classList.remove("show");
        },

        applyFilterDrawer: function() {
            filterIndustry = document.getElementById("filterIndustrySelect").value;
            filterOwner = document.getElementById("filterOwnerSelect").value;
            filterDealsRange = document.getElementById("filterDealsRangeSelect").value;

            window.companiesApp.closeFilterPopover();
            currentPage = 1;
            fetchCompanies();
        },

        resetFilters: function() {
            filterIndustry = "All";
            filterOwner = "All";
            filterDealsRange = "All";
            searchQuery = "";

            const searchInput = document.getElementById("companiesSearchInput");
            if (searchInput) searchInput.value = "";
            if (document.getElementById("filterIndustrySelect")) document.getElementById("filterIndustrySelect").value = "All";
            if (document.getElementById("filterOwnerSelect")) document.getElementById("filterOwnerSelect").value = "All";
            if (document.getElementById("filterDealsRangeSelect")) document.getElementById("filterDealsRangeSelect").value = "All";

            window.companiesApp.closeFilterPopover();
            currentPage = 1;
            fetchCompanies();
        },

        removeFilterChip: function(type) {
            if (type === "industry") filterIndustry = "All";
            if (type === "owner") filterOwner = "All";
            if (type === "deals") filterDealsRange = "All";

            if (document.getElementById("filterIndustrySelect")) document.getElementById("filterIndustrySelect").value = filterIndustry;
            if (document.getElementById("filterOwnerSelect")) document.getElementById("filterOwnerSelect").value = filterOwner;
            if (document.getElementById("filterDealsRangeSelect")) document.getElementById("filterDealsRangeSelect").value = filterDealsRange;

            currentPage = 1;
            fetchCompanies();
        },

        openAddTaskModal: function() {
            const form = document.getElementById("compAddTaskForm");
            if (form) form.reset();
            const modal = document.getElementById("companyAddTaskModal");
            if (modal) modal.classList.add("show");
        },

        closeAddTaskModal: function() {
            const modal = document.getElementById("companyAddTaskModal");
            if (modal) modal.classList.remove("show");
        },

        toggleActiveCompanyStatus: function() {
            if (!activeCompanyForDetails) return;
            const comp = activeCompanyForDetails;
            const currentActive = Number(comp.is_active) === 1;
            const newActive = currentActive ? 0 : 1;
            window.companiesApp.closeMoreDropdown();

            fetch('api/companies.php?action=toggle_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: comp.id,
                    is_active: newActive
                })
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    showCompaniesToast(res.message || 'Error updating company status.');
                    return;
                }
                showCompaniesToast(newActive === 1 ? "Company marked as active." : "Company marked as inactive.");
                fetchCompanies(() => {
                    window.companiesApp.openDetails(comp.id);
                });
            })
            .catch(err => {
                console.error('Status update error:', err);
                showCompaniesToast('Network error updating company status.');
            });
        },

        archiveActiveCompany: function() {
            return this.toggleActiveCompanyStatus();
        },

        deleteActiveCompany: function() {
            if (!activeCompanyForDetails) return;
            const comp = activeCompanyForDetails;
            window.companiesApp.closeMoreDropdown();
            window.companiesApp.openCompanyModal('delete-company', comp.id);
        },

        confirmDeleteCompany: function(companyId) {
            fetch('api/companies.php?action=delete&id=' + encodeURIComponent(companyId), { method: 'POST' })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        showCompaniesToast(res.message || 'Error deleting company.');
                        return;
                    }
                    showCompaniesToast("Company deleted successfully.");
                    window.companiesApp.closeCompanyModal();
                    window.companiesApp.closeDetails();
                    fetchCompanies();
                })
                .catch(err => {
                    console.error('Delete company error:', err);
                    showCompaniesToast('Network error while deleting company.');
                });
        },

        copyToClipboard: function(text) {
            navigator.clipboard.writeText(text).then(() => {
                showCompaniesToast(`Copied '${text}' to clipboard!`);
            });
        },

        exportVisibleCompaniesCSV: function() {
            const params = new URLSearchParams({
                action: 'export_csv',
                search: searchQuery,
                relationship: cardFilter,
                industry: filterIndustry,
                owner: filterOwner
            });
            window.location.href = 'api/companies.php?' + params.toString();
        },

        handleImportFile: function(e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('csv_file', file);

            showCompaniesToast('Importing companies CSV...');
            fetch('api/companies.php?action=import_csv', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    showCompaniesToast(res.message || 'Error importing CSV.');
                    return;
                }
                showCompaniesToast(res.message || 'Import successful!');
                e.target.value = '';
                fetchCompanies();
            })
            .catch(err => {
                console.error('Import error:', err);
                showCompaniesToast('Network error during import.');
            });
        },

        // Sub-entity operations
        submitAddContact: function(e) {
            e.preventDefault();
            if (!activeCompanyForDetails) return;

            const cId = activeCompanyForDetails.id;
            const first = document.getElementById("newCntFirst") ? document.getElementById("newCntFirst").value.trim() : "";
            const last = document.getElementById("newCntLast") ? document.getElementById("newCntLast").value.trim() : "";
            const job = document.getElementById("newCntJob") ? document.getElementById("newCntJob").value.trim() : "";
            const email = document.getElementById("newCntEmail") ? document.getElementById("newCntEmail").value.trim() : "";
            const phone = document.getElementById("newCntPhone") ? document.getElementById("newCntPhone").value.trim() : "";
            const rel = document.getElementById("newCntRel") ? document.getElementById("newCntRel").value : "Customer";
            const ownerId = document.getElementById("newCntOwner") ? document.getElementById("newCntOwner").value : "";
            const source = document.getElementById("newCntSource") ? document.getElementById("newCntSource").value.trim() : "";
            const loc = document.getElementById("newCntLoc") ? document.getElementById("newCntLoc").value.trim() : "";
            const prefChannel = document.getElementById("newCntPreferredChannel") ? document.getElementById("newCntPreferredChannel").value : "";
            const linkedin = document.getElementById("newCntLinkedin") ? document.getElementById("newCntLinkedin").value.trim() : "";
            const isPrimary = document.getElementById("newCntIsPrimary") ? (document.getElementById("newCntIsPrimary").checked ? 1 : 0) : 0;

            if (!first || !last) {
                showCompaniesToast("First Name and Last Name are required.");
                return;
            }
            if (!email) {
                showCompaniesToast("Email Address is required.");
                return;
            }

            fetch('api/companies.php?action=add_contact', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    company_id: cId,
                    first_name: first,
                    last_name: last,
                    name: `${first} ${last}`.trim(),
                    job_title: job,
                    email: email,
                    phone: phone,
                    relationship: rel,
                    owner_id: ownerId ? parseInt(ownerId, 10) : null,
                    source: source,
                    location: loc,
                    preferred_channel: prefChannel,
                    linkedin: linkedin,
                    is_primary: isPrimary
                })
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    showCompaniesToast(res.message || 'Error adding contact.');
                    return;
                }
                showCompaniesToast("Contact created successfully.");
                window.companiesApp.closeCompanyModal();
                fetchCompanies(() => {
                    window.companiesApp.openDetails(cId);
                });
            })
            .catch(err => {
                console.error('Add contact error:', err);
                showCompaniesToast('Network error while adding contact.');
            });
        },

        submitCreateDeal: function(e) {
            e.preventDefault();
            if (!activeCompanyForDetails) return;

            const cId = activeCompanyForDetails.id;
            const name = document.getElementById("createDealName").value.trim();
            const value = document.getElementById("createDealValue").value.trim();
            const stage = document.getElementById("createDealStage").value;
            const prob = document.getElementById("createDealProb").value;
            const close = document.getElementById("createDealClose").value;

            fetch('api/companies.php?action=create_deal', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    company_id: cId,
                    name: name,
                    value: value,
                    stage: stage,
                    probability: prob,
                    close_date: close
                })
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    showCompaniesToast(res.message || 'Error creating deal.');
                    return;
                }
                showCompaniesToast("Deal created successfully.");
                window.companiesApp.closeCompanyModal();
                window.companiesApp.openDetails(cId);
                fetchCompanies();
            });
        },

        submitLogActivity: function(e) {
            e.preventDefault();
            if (!activeCompanyForDetails) return;

            const cId = activeCompanyForDetails.id;
            const type = document.getElementById("logActivityType") ? document.getElementById("logActivityType").value : 'Call';
            const title = document.getElementById("logActivityTitle") ? document.getElementById("logActivityTitle").value.trim() : '';
            const desc = document.getElementById("logActivityDesc") ? document.getElementById("logActivityDesc").value.trim() : '';

            if (!title) {
                showCompaniesToast("Activity summary is required.");
                return;
            }

            fetch('api/companies.php?action=log_activity', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    company_id: cId,
                    type: type,
                    title: title,
                    description: desc
                })
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    showCompaniesToast(res.message || 'Error logging activity.');
                    return;
                }
                showCompaniesToast("Activity logged successfully.");
                window.companiesApp.closeCompanyModal();
                window.companiesApp.openDetails(cId);
            });
        },

        toggleTaskDone: function(companyId, taskId, isChecked) {
            fetch('api/companies.php?action=toggle_task', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ task_id: taskId, done: isChecked })
            })
            .then(r => r.json())
            .then(res => {
                if (activeCompanyForDetails) {
                    window.companiesApp.openDetails(companyId);
                }
            });
        },

        openEditTaskModal: function(companyId, taskId) {
            const task = activeDrawerData?.tasks?.find(t => String(t.id) === String(taskId));
            if (!task) return;
            window.companiesApp.openCompanyModal('edit-task', taskId, null, task);
        },

        openDeleteTaskModal: function(companyId, taskId) {
            window.companiesApp.openCompanyModal('delete-task', taskId);
        },

        confirmDeleteTask: function(taskId) {
            fetch('api/companies.php?action=delete_task', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ task_id: taskId })
            })
            .then(r => r.json())
            .then(res => {
                showCompaniesToast("Task deleted.");
                window.companiesApp.closeCompanyModal();
                if (activeCompanyForDetails) {
                    window.companiesApp.openDetails(activeCompanyForDetails.id);
                }
            });
        },

        submitEditTask: function(e, taskId) {
            e.preventDefault();
            const title = document.getElementById("editTaskTitleInput").value.trim();
            const prio = document.getElementById("editTaskPrioritySelect").value;
            const due = document.getElementById("editTaskDueDateInput").value.trim();
            const desc = document.getElementById("editTaskNotesInput").value.trim();

            fetch('api/companies.php?action=edit_task', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    task_id: taskId,
                    title: title,
                    priority: prio,
                    due_date: due,
                    description: desc
                })
            })
            .then(r => r.json())
            .then(res => {
                showCompaniesToast("Task updated.");
                window.companiesApp.closeCompanyModal();
                if (activeCompanyForDetails) {
                    window.companiesApp.openDetails(activeCompanyForDetails.id);
                }
            });
        },

        openAddNoteModal: function() {
            window.companiesApp.openCompanyModal('add-note');
        },

        submitAddNote: function(e) {
            e.preventDefault();
            if (!activeCompanyForDetails) return;

            const cId = activeCompanyForDetails.id;
            const content = document.getElementById("noteTextInput").value.trim();
            const title = document.getElementById("noteTitleInput") ? document.getElementById("noteTitleInput").value.trim() : 'Note';

            fetch('api/companies.php?action=add_note', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ company_id: cId, title: title, content: content })
            })
            .then(r => r.json())
            .then(res => {
                showCompaniesToast("Note added.");
                window.companiesApp.closeCompanyModal();
                window.companiesApp.openDetails(cId);
            });
        },

        openEditNoteModal: function(companyId, noteId) {
            const note = activeDrawerData?.notes?.find(n => String(n.id) === String(noteId));
            if (!note) return;
            window.companiesApp.openCompanyModal('edit-note', noteId, null, note);
        },

        submitEditNote: function(e, noteId) {
            e.preventDefault();
            const content = document.getElementById("editNoteTextInput").value.trim();
            const title = document.getElementById("editNoteTitleInput") ? document.getElementById("editNoteTitleInput").value.trim() : '';

            fetch('api/companies.php?action=edit_note', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note_id: noteId, title: title, content: content })
            })
            .then(r => r.json())
            .then(res => {
                showCompaniesToast("Note updated.");
                window.companiesApp.closeCompanyModal();
                if (activeCompanyForDetails) {
                    window.companiesApp.openDetails(activeCompanyForDetails.id);
                }
            });
        },

        openDeleteNoteModal: function(companyId, noteId) {
            window.companiesApp.openCompanyModal('delete-note', noteId);
        },

        confirmDeleteNote: function(noteId) {
            fetch('api/companies.php?action=delete_note', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note_id: noteId })
            })
            .then(r => r.json())
            .then(res => {
                showCompaniesToast("Note deleted.");
                window.companiesApp.closeCompanyModal();
                if (activeCompanyForDetails) {
                    window.companiesApp.openDetails(activeCompanyForDetails.id);
                }
            });
        },

        togglePinNote: function(companyId, noteId) {
            fetch('api/companies.php?action=toggle_pin_note', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ note_id: noteId })
            })
            .then(r => r.json())
            .then(res => {
                if (activeCompanyForDetails) {
                    window.companiesApp.openDetails(companyId);
                }
            });
        },

        filterCompanyNotes: function(query) {
            compNotesSearchQuery = query.trim();
            const container = document.getElementById("compNotesContainer");
            if (container && activeDrawerData) {
                container.innerHTML = renderNotesCards(activeDrawerData.notes || [], activeCompanyForDetails?.id);
            }
        },

        openCompanyModal: function(type, id, triggerBtn, dataObj) {
            const modal = document.getElementById("companyActionModal");
            const panel = document.getElementById("companyActionModalPanel");
            if (!modal || !panel) return;

            activeCompanyModal = type;
            activeCompanyModalData = dataObj || id;
            companyModalTrigger = triggerBtn;

            panel.className = "company-action-panel";
            let html = "";

            if (type === "add-contact") {
                html = renderAddContactModal();
            } else if (type === "create-deal") {
                html = renderCreateDealModal();
            } else if (type === "view-deal") {
                panel.classList.add("wide-modal");
                html = renderViewDealModal(id, dataObj);
            } else if (type === "log-activity") {
                html = renderLogActivityModal();
            } else if (type === "edit-task") {
                html = renderEditTaskModal(id, dataObj);
            } else if (type === "delete-task") {
                panel.classList.add("compact-modal");
                html = renderDeleteTaskModal(id);
            } else if (type === "add-note") {
                html = renderAddNoteModal();
            } else if (type === "edit-note") {
                html = renderEditNoteModal(id, dataObj);
            } else if (type === "delete-note") {
                panel.classList.add("compact-modal");
                html = renderDeleteNoteModal(id);
            } else if (type === "delete-company") {
                panel.classList.add("compact-modal");
                html = renderDeleteCompanyModal(id);
            } else if (type === "contact-call") {
                panel.classList.add("compact-modal");
                html = renderCallModal(id);
            } else if (type === "contact-whatsapp") {
                html = renderWhatsAppModal(id);
            } else if (type === "contact-email") {
                panel.classList.add("wide-modal");
                html = renderEmailModal(id);
            }

            if (!html) return;
            panel.innerHTML = html;
            modal.classList.add("is-open");

            setTimeout(() => {
                const firstInput = panel.querySelector("input:not([type='hidden']), select, textarea, button");
                if (firstInput) firstInput.focus();
            }, 50);
        },

        closeCompanyModal: function() {
            const modal = document.getElementById("companyActionModal");
            const panel = document.getElementById("companyActionModalPanel");
            if (modal) modal.classList.remove("is-open");
            if (panel) panel.innerHTML = "";
        }
    };

    // --- Modal Templates ---
    function renderAddContactModal() {
        const companyName = activeCompanyForDetails ? (activeCompanyForDetails.name || '') : '';
        const companyId = activeCompanyForDetails ? activeCompanyForDetails.id : '';

        let ownerOpts = "";
        if (availableOwners && availableOwners.length > 0) {
            ownerOpts = availableOwners.map(u => `<option value="${u.id}">${escapeHtml(u.name)}</option>`).join('');
        } else {
            const ownerSel = document.getElementById("addCompanyOwner");
            if (ownerSel) {
                Array.from(ownerSel.options).forEach(opt => {
                    const oId = opt.getAttribute("data-id");
                    if (oId) {
                        ownerOpts += `<option value="${oId}">${escapeHtml(opt.text)}</option>`;
                    }
                });
            }
        }

        return `
            <div class="modal-header">
                <h3 class="modal-title">Add New Contact</h3>
                <button type="button" class="modal-close-btn" onclick="window.companiesApp.closeCompanyModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="childAddContactForm" onsubmit="window.companiesApp.submitAddContact(event)" style="display:flex; flex-direction:column; flex:1; margin:0;">
                <div class="modal-body" style="padding: 16px 20px;">
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
                        <div>
                            <label class="form-label">Company Name *</label>
                            <input type="text" class="input-control" id="newCntCompany" value="${escapeHtml(companyName)}" readonly style="background-color: var(--bg-surface, #F8FAFC); cursor: not-allowed;">
                            <input type="hidden" id="newCntCompanyId" value="${companyId}">
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeCompanyModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Create Contact</button>
                </div>
            </form>
        `;
    }

    function renderDeleteCompanyModal(id) {
        const comp = (activeCompanyForDetails && String(activeCompanyForDetails.id) === String(id))
            ? activeCompanyForDetails
            : (allCompanies.find(c => String(c.id) === String(id)) || {});
        const companyName = comp.name || 'this company';

        return `
            <div class="modal-header">
                <h3 class="modal-title" style="color: #DC2626;">Delete Company</h3>
                <button type="button" class="modal-close-btn" onclick="window.companiesApp.closeCompanyModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body" style="padding: 16px 20px;">
                <p style="font-size: 13.5px; color: var(--text-body); margin-bottom: 8px;">
                    Are you sure you want to delete <strong>${escapeHtml(companyName)}</strong>?
                </p>
                <p style="font-size: 12px; color: var(--text-muted); margin: 0;">
                    Associated deals, tasks, and contact associations will also be removed.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeCompanyModal()">Cancel</button>
                <button type="button" class="btn btn-sm" style="background-color: #DC2626; color: white; border: none; font-weight: 600;" onclick="window.companiesApp.confirmDeleteCompany('${id}')">Delete Company</button>
            </div>
        `;
    }

    function renderCreateDealModal() {
        return `
            <div class="modal-header">
                <h3 class="modal-title">Create Deal</h3>
                <button type="button" class="modal-close-btn" onclick="window.companiesApp.closeCompanyModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="childCreateDealForm" onsubmit="window.companiesApp.submitCreateDeal(event)" style="display:flex; flex-direction:column; flex:1; margin:0;">
                <div class="modal-body">
                    <div style="margin-bottom:16px;">
                        <label class="input-label">Deal Name *</label>
                        <input type="text" class="input-control" id="createDealName" required placeholder="e.g. Enterprise License">
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label class="input-label">Deal Value ($) *</label>
                            <input type="text" class="input-control" id="createDealValue" required placeholder="25000">
                        </div>
                        <div>
                            <label class="input-label">Stage</label>
                            <select class="input-control" id="createDealStage">
                                <option value="Prospect">Prospect</option>
                                <option value="Qualified">Qualified</option>
                                <option value="Proposal" selected>Proposal</option>
                                <option value="Negotiation">Negotiation</option>
                                <option value="Won">Won</option>
                                <option value="Lost">Lost</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label class="input-label">Probability (%)</label>
                            <input type="number" class="input-control" id="createDealProb" min="0" max="100" value="60">
                        </div>
                        <div>
                            <label class="input-label">Expected Close Date</label>
                            <input type="date" class="input-control" id="createDealClose">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeCompanyModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Create Deal</button>
                </div>
            </form>
        `;
    }

    function renderLogActivityModal() {
        return `
            <div class="modal-header">
                <h3 class="modal-title">Log Activity</h3>
                <button type="button" class="modal-close-btn" onclick="window.companiesApp.closeCompanyModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <form onsubmit="window.companiesApp.submitLogActivity(event)" style="display:flex; flex-direction:column; flex:1; margin:0;">
                <div class="modal-body">
                    <div style="margin-bottom:14px;">
                        <label class="input-label">Activity Type *</label>
                        <select class="input-control" id="logActivityType">
                            <option value="Call">Call</option>
                            <option value="Meeting">Meeting</option>
                            <option value="Email">Email</option>
                            <option value="Note">Note</option>
                        </select>
                    </div>
                    <div style="margin-bottom:14px;">
                        <label class="input-label">Summary *</label>
                        <input type="text" class="input-control" id="logActivityTitle" required placeholder="e.g. Call regarding pilot proposal">
                    </div>
                    <div style="margin-bottom:14px;">
                        <label class="input-label">Details</label>
                        <textarea class="input-control" id="logActivityDesc" style="height:80px; resize:vertical;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeCompanyModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Activity</button>
                </div>
            </form>
        `;
    }

    function renderAddNoteModal() {
        return `
            <div class="modal-header">
                <h3 class="modal-title">Add Note</h3>
                <button type="button" class="modal-close-btn" onclick="window.companiesApp.closeCompanyModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <form onsubmit="window.companiesApp.submitAddNote(event)">
                <div class="modal-body">
                    <div style="margin-bottom:12px;">
                        <label class="input-label">Note Title</label>
                        <input type="text" class="input-control" id="noteTitleInput" placeholder="Meeting recap / Key note">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="input-label">Note Content *</label>
                        <textarea class="input-control" id="noteTextInput" style="height:100px; resize:vertical;" required placeholder="Enter note..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeCompanyModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Note</button>
                </div>
            </form>
        `;
    }

    function renderEditNoteModal(id, note) {
        return `
            <div class="modal-header">
                <h3 class="modal-title">Edit Note</h3>
                <button type="button" class="modal-close-btn" onclick="window.companiesApp.closeCompanyModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <form onsubmit="window.companiesApp.submitEditNote(event, '${id}')">
                <div class="modal-body">
                    <div style="margin-bottom:12px;">
                        <label class="input-label">Note Title</label>
                        <input type="text" class="input-control" id="editNoteTitleInput" value="${escapeHtml(note?.title || '')}">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label class="input-label">Note Content *</label>
                        <textarea class="input-control" id="editNoteTextInput" style="height:100px; resize:vertical;" required>${escapeHtml(note?.content || '')}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeCompanyModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Update Note</button>
                </div>
            </form>
        `;
    }

    function renderDeleteNoteModal(id) {
        return `
            <div class="modal-header">
                <h3 class="modal-title">Delete Note</h3>
                <button type="button" class="modal-close-btn" onclick="window.companiesApp.closeCompanyModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body" style="padding: 16px;">
                <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">Are you sure you want to delete this note?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeCompanyModal()">Cancel</button>
                <button type="button" class="btn btn-sm" style="background-color: #DC2626; color: white; border: none; font-weight: 600;" onclick="window.companiesApp.confirmDeleteNote('${id}')">Delete Note</button>
            </div>
        `;
    }

    function renderEditTaskModal(id, task) {
        return `
            <div class="modal-header">
                <h3 class="modal-title">Edit Task</h3>
                <button type="button" class="modal-close-btn" onclick="window.companiesApp.closeCompanyModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <form onsubmit="window.companiesApp.submitEditTask(event, '${id}')" style="display: flex; flex-direction: column; flex: 1; margin: 0;">
                <div class="modal-body" style="padding: 16px;">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label class="form-label">Task Title *</label>
                        <input type="text" class="input-control input-sm" id="editTaskTitleInput" required value="${escapeHtml(task?.title || '')}">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                        <div>
                            <label class="form-label">Priority</label>
                            <select class="input-control input-sm" id="editTaskPrioritySelect">
                                <option value="High" ${task?.priority === 'high' ? 'selected' : ''}>High</option>
                                <option value="Medium" ${task?.priority === 'medium' ? 'selected' : ''}>Medium</option>
                                <option value="Low" ${task?.priority === 'low' ? 'selected' : ''}>Low</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Due Date</label>
                            <input type="date" class="input-control input-sm" id="editTaskDueDateInput" value="${task?.due_date ? task.due_date.slice(0,10) : ''}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea class="input-control" id="editTaskNotesInput" style="height: 60px; font-size: 12px; resize: none;">${escapeHtml(task?.description || '')}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeCompanyModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Update Task</button>
                </div>
            </form>
        `;
    }

    function renderDeleteTaskModal(id) {
        return `
            <div class="modal-header">
                <h3 class="modal-title">Delete Task</h3>
                <button type="button" class="modal-close-btn" onclick="window.companiesApp.closeCompanyModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body" style="padding: 16px;">
                <p style="font-size: 13px; color: var(--text-secondary); margin: 0;">Are you sure you want to delete this task?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeCompanyModal()">Cancel</button>
                <button type="button" class="btn btn-sm" style="background-color: #DC2626; color: white; border: none; font-weight: 600;" onclick="window.companiesApp.confirmDeleteTask('${id}')">Delete Task</button>
            </div>
        `;
    }

    function renderViewDealModal(dealId) {
        const deal = activeDrawerData?.deals?.find(d => String(d.id) === String(dealId));
        return `
            <div class="modal-header">
                <h3 class="modal-title">${escapeHtml(deal?.name || 'Deal Details')}</h3>
                <button type="button" class="modal-close-btn" onclick="window.companiesApp.closeCompanyModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div><span class="contacts-info-label">Deal Value:</span> <strong>$${parseFloat(deal?.value || 0).toLocaleString()}</strong></div>
                    <div><span class="contacts-info-label">Stage:</span> <strong>${escapeHtml(deal?.stage || '—')}</strong></div>
                    <div><span class="contacts-info-label">Probability:</span> <strong>${deal?.probability || 0}%</strong></div>
                    <div><span class="contacts-info-label">Close Date:</span> <strong>${deal?.close_date || 'Not set'}</strong></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeCompanyModal()">Close</button>
            </div>
        `;
    }

    function renderCallModal(contactId) {
        const contact = activeDrawerData?.contacts?.find(c => String(c.id) === String(contactId));
        const phone = contact?.phone || '';
        return `
            <div class="modal-header">
                <h3 class="modal-title">Call Contact</h3>
                <button type="button" class="modal-close-btn" onclick="window.companiesApp.closeCompanyModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body" style="padding: 16px;">
                <p style="font-size: 14px; font-weight: 600; color: var(--text-heading); margin-bottom: 4px;">${escapeHtml(contact?.name || 'Contact')}</p>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">${escapeHtml(phone || 'No phone number available')}</p>
                ${phone ? `<a href="tel:${phone.replace(/[^0-9+]/g, '')}" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:6px;">Call ${escapeHtml(phone)}</a>` : ''}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeCompanyModal()">Close</button>
            </div>
        `;
    }

    function renderWhatsAppModal(contactId) {
        const contact = activeDrawerData?.contacts?.find(c => String(c.id) === String(contactId));
        const phone = (contact?.phone || '').replace(/[^0-9+]/g, '');
        return `
            <div class="modal-header">
                <h3 class="modal-title">WhatsApp Message</h3>
                <button type="button" class="modal-close-btn" onclick="window.companiesApp.closeCompanyModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body" style="padding: 16px;">
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">To: <strong>${escapeHtml(contact?.name || '')}</strong> (${escapeHtml(contact?.phone || '')})</p>
                <textarea class="input-control" id="waMessageText" style="height: 100px; resize: vertical;" placeholder="Type your WhatsApp message..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeCompanyModal()">Cancel</button>
                <button type="button" class="btn btn-sm" style="background-color:#25D366; color:white;" onclick="const msg = document.getElementById('waMessageText').value; window.open('https://wa.me/${phone}?text=' + encodeURIComponent(msg), '_blank'); window.companiesApp.closeCompanyModal();">Send via WhatsApp</button>
            </div>
        `;
    }

    function renderEmailModal(contactId) {
        const contact = activeDrawerData?.contacts?.find(c => String(c.id) === String(contactId));
        const email = contact?.email || '';
        return `
            <div class="modal-header">
                <h3 class="modal-title">Send Email</h3>
                <button type="button" class="modal-close-btn" onclick="window.companiesApp.closeCompanyModal()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal-body" style="padding: 16px;">
                <div style="margin-bottom: 12px;">
                    <label class="input-label">To:</label>
                    <input type="text" class="input-control" value="${escapeHtml(contact?.name || '')} <${escapeHtml(email)}>" disabled>
                </div>
                <div style="margin-bottom: 12px;">
                    <label class="input-label">Subject:</label>
                    <input type="text" class="input-control" id="emailSubjectInput" placeholder="Subject">
                </div>
                <div style="margin-bottom: 12px;">
                    <label class="input-label">Message:</label>
                    <textarea class="input-control" id="emailBodyInput" style="height: 120px; resize: vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.companiesApp.closeCompanyModal()">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="const sub = document.getElementById('emailSubjectInput').value; const body = document.getElementById('emailBodyInput').value; window.location.href = 'mailto:${email}?subject=' + encodeURIComponent(sub) + '&body=' + encodeURIComponent(body); window.companiesApp.closeCompanyModal();">Open Email App</button>
            </div>
        `;
    }

    // --- Helpers ---
    function company_initials(name) {
        if (!name) return "CO";
        const parts = name.trim().split(/\s+/);
        if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
        return name.slice(0, 2).toUpperCase();
    }

    function company_color(str) {
        const palette = ['#7C3AED', '#0284C7', '#059669', '#EA580C', '#DC2626', '#4F46E5', '#0891B2', '#D97706'];
        if (!str) return palette[0];
        let hash = 0;
        for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
        return palette[Math.abs(hash) % palette.length];
    }

    function company_time_ago(datetime) {
        if (!datetime) return "Never";
        const d = new Date(String(datetime).replace(' ', 'T'));
        if (isNaN(d.getTime())) return "Recently";
        const diff = Math.floor((Date.now() - d.getTime()) / 1000);
        if (diff < 60) return "Just now";
        if (diff < 3600) return Math.floor(diff / 60) + "m ago";
        if (diff < 86400) return Math.floor(diff / 3600) + "h ago";
        if (diff < 172800) return "Yesterday";
        if (diff < 604800) return Math.floor(diff / 86400) + "d ago";
        return d.toLocaleDateString();
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    function restoreKeyboardFocus() {
        if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
            lastFocusedElement.focus();
            lastFocusedElement = null;
        }
    }

    function updateBulkToolbarState() {
        const toolbar = document.getElementById("companiesBulkToolbar");
        const countText = document.getElementById("companiesSelectedCountText");
        const count = selectedCompanyIds.size;
        if (countText) countText.textContent = count;
        if (toolbar) {
            toolbar.style.display = count > 0 ? "flex" : "none";
        }
    }

    window.showCompaniesToast = function(msg) {
        const toast = document.createElement("div");
        toast.style.cssText = "position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background-color: #101828; color: #ffffff; padding: 10px 18px; border-radius: 999px; font-size: 13px; font-weight: 500; box-shadow: 0 10px 15px rgba(0,0,0,0.2); z-index: 9999; pointer-events: none; transition: opacity 0.3s ease;";
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = "0";
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    };

    // --- Init on DOM Ready ---
    document.addEventListener("DOMContentLoaded", function() {
        applyDensityUI();
        setupEventListeners();
        fetchCompanies();
    });
})();