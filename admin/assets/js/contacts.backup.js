/**
 * NexFlow CRM Contacts Management & Contact View Drawer Controller JavaScript
 * Handles search, tabs, sorting, list/grid view, bulk actions, pagination,
 * WAI-ARIA Contact View drawer (Overview, Activity, Deals, Tasks, Notes),
 * refined communication actions (Call, WhatsApp, Email), clipboard copy,
 * focus trapping/restoration, edit mode, task creation, note pinning & localStorage persistence.
 */

(function() {
    const STORAGE_KEY = "NexFlow_contacts_state_v1";
    const NOTES_KEY = "NexFlow_contact_notes";
    const TASKS_KEY = "NexFlow_contact_tasks";
    const ACTIVITIES_KEY = "NexFlow_contact_activities";
    const DEALS_KEY = "NexFlow_contact_deals_v1";

    let allContacts = [];
    let filteredContacts = [];
    let currentRelationship = "All";
    let searchQuery = "";
    let sortBy = "name-asc";
    let viewMode = "list"; // 'list' or 'grid'
    let currentPage = 1;
    let rowsPerPage = 10;
    let selectedContactIds = new Set();

    let activeContactForDetails = null;
    let activeTabName = "overview";
    let lastFocusedElement = null;
    let commTriggerElement = null;

    let contactNotesStore = {};
    let contactTasksStore = {};
    let contactActivitiesStore = {};
    let contactDealsStore = {};

    function initContactsPage() {
        if (window._contactsPageInitialized) return;
        window._contactsPageInitialized = true;
        initContactsData();
        initStores();
        setupEventListeners();
        renderContacts();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initContactsPage);
    } else {
        initContactsPage();
    }

    function initContactsData() {
        if (window.CONTACTS_MOCK_DATA && Array.isArray(window.CONTACTS_MOCK_DATA)) {
            allContacts = [...window.CONTACTS_MOCK_DATA];
        } else {
            allContacts = [];
        }

        const custom = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
        if (Array.isArray(custom) && custom.length > 0) {
            custom.forEach(c => {
                if (!c || !c.id || !c.name) return; // skip invalid localStorage entries
                const idx = allContacts.findIndex(existing => existing.id === c.id);
                if (idx >= 0) {
                    allContacts[idx] = c;
                } else {
                    allContacts.unshift(c);
                }
            });
        }

        filteredContacts = [...allContacts];
    }

    function initStores() {
        contactNotesStore = JSON.parse(localStorage.getItem(NOTES_KEY) || "{}");
        contactTasksStore = JSON.parse(localStorage.getItem(TASKS_KEY) || "{}");
        contactActivitiesStore = JSON.parse(localStorage.getItem(ACTIVITIES_KEY) || "{}");
        contactDealsStore = JSON.parse(localStorage.getItem(DEALS_KEY) || "{}");
    }

    function setupEventListeners() {
        // Search Input
        const searchInput = document.getElementById("contactsSearchInput");
        if (searchInput) {
            searchInput.addEventListener("input", function(e) {
                searchQuery = e.target.value.trim().toLowerCase();
                currentPage = 1;
                applyFilterAndSort();
            });
        }

        // Sort Dropdown
        const sortSelect = document.getElementById("contactsSortSelect");
        if (sortSelect) {
            sortSelect.addEventListener("change", function(e) {
                sortBy = e.target.value;
                applyFilterAndSort();
            });
        }

        // Select All Checkbox
        const selectAllCb = document.getElementById("contactsSelectAll");
        if (selectAllCb) {
            selectAllCb.addEventListener("change", function(e) {
                const isChecked = e.target.checked;
                selectedContactIds.clear();
                if (isChecked) {
                    getPagedContacts().forEach(c => selectedContactIds.add(c.id));
                }
                updateBulkToolbarState();
                renderTableRows();
            });
        }

        // Edit Contact Form Submit
        const editForm = document.getElementById("editContactForm");
        if (editForm) {
            editForm.addEventListener("submit", function(e) {
                e.preventDefault();
                saveEditContactSubmit();
            });
        }

        // Add Contact Form Submit
        const addForm = document.getElementById("addContactForm");
        if (addForm) {
            addForm.addEventListener("submit", function(e) {
                e.preventDefault();
                saveContactFormSubmit();
            });
        }

        // Add Task Form Submit
        const addTaskForm = document.getElementById("addTaskForm");
        if (addTaskForm) {
            addTaskForm.addEventListener("submit", function(e) {
                e.preventDefault();
                saveTaskFormSubmit();
            });
        }

        // Add Note Form Submit
        const addNoteForm = document.getElementById("addNoteForm");
        if (addNoteForm) {
            addNoteForm.addEventListener("submit", function(e) {
                e.preventDefault();
                saveNoteFormSubmit();
            });
        }

        // Log Activity Form Submit
        const logActivityForm = document.getElementById("logActivityForm");
        if (logActivityForm) {
            logActivityForm.addEventListener("submit", function(e) {
                e.preventDefault();
                saveLogActivitySubmit();
            });
        }

        // Create Deal Form Submit
        const createDealForm = document.getElementById("createDealForm");
        if (createDealForm) {
            createDealForm.addEventListener("submit", function(e) {
                e.preventDefault();
                saveCreateDealSubmit();
            });
        }

        // WAI-ARIA Left/Right Arrow Keyboard Navigation on Drawer Tabs
        const tabList = document.querySelector(".contact-view-tabs[role='tablist']");
        if (tabList) {
            tabList.addEventListener("keydown", function(e) {
                const tabs = Array.from(tabList.querySelectorAll("[role='tab']"));
                const index = tabs.indexOf(document.activeElement);
                if (index < 0) return;

                let nextIndex = index;
                if (e.key === "ArrowRight") {
                    nextIndex = (index + 1) % tabs.length;
                    e.preventDefault();
                } else if (e.key === "ArrowLeft") {
                    nextIndex = (index - 1 + tabs.length) % tabs.length;
                    e.preventDefault();
                }

                if (nextIndex !== index) {
                    tabs[nextIndex].focus();
                    const tabName = tabs[nextIndex].id.replace("tab-", "");
                    window.contactsApp.switchDetailsTab(tabName);
                }
            });
        }

        // NOTE: Contact Details is now opened ONLY via three-dot menu → View Details.
        // The contact-view-trigger delegated listener has been intentionally removed.

        // Global Backdrop Clicks for Modals and Drawers
        document.addEventListener("click", function(e) {
            // contact-action-modal: click on the overlay backdrop itself (not the inner panel)
            if (e.target.classList.contains("contact-action-modal") && activeModalId) {
                closeModal(activeModalId);
                return;
            }
            if (e.target.classList.contains("contacts-drawer-overlay")) {
                if (e.target.id === "contactsDetailsDrawer") window.contactsApp.closeDetails();
                if (e.target.id === "contactsAddDrawer") window.contactsApp.closeAddDrawer();
                if (e.target.id === "contactsFilterDrawer") window.contactsApp.closeFilterDrawer();
            }
        });

        // Global ESC Key Handling with Priority
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                if (activeModalId) {
                    closeModal(activeModalId);
                } else if (document.getElementById("contactsDetailsDrawer") && document.getElementById("contactsDetailsDrawer").classList.contains("show")) {
                    window.contactsApp.closeDetails();
                } else {
                    window.contactsApp.closeAddDrawer();
                    window.contactsApp.closeFilterDrawer();
                    window.contactsApp.closeMoreDropdown();
                    document.querySelectorAll(".dropdown-menu.row-menu.show").forEach(m => m.classList.remove("show"));
                }
            }
        });
    }

    function applyFilterAndSort() {
        filteredContacts = allContacts.filter(c => {
            const cRel = (c.relationship || "").toString().trim().toLowerCase();
            const selRel = currentRelationship.toString().trim().toLowerCase();
            const matchesRel = (selRel === "all" || cRel === selRel);
            const matchesQuery = !searchQuery || (
                (c.name && c.name.toLowerCase().includes(searchQuery)) ||
                (c.company && c.company.toLowerCase().includes(searchQuery)) ||
                (c.email && c.email.toLowerCase().includes(searchQuery)) ||
                (c.phone && c.phone.includes(searchQuery)) ||
                (c.industry && c.industry.toLowerCase().includes(searchQuery))
            );
            return matchesRel && matchesQuery;
        });

        filteredContacts.sort((a, b) => {
            if (sortBy === "name-asc") return a.name.localeCompare(b.name);
            if (sortBy === "name-desc") return b.name.localeCompare(a.name);
            if (sortBy === "company-asc") return a.company.localeCompare(b.company);
            if (sortBy === "deals-desc") return (b.openDealsValue || 0) - (a.openDealsValue || 0);
            return 0;
        });

        updateHeaderCounts();
        renderContacts();
    }

    function updateHeaderCounts() {
        const totalBadge = document.getElementById("contactsTotalCountBadge");
        if (totalBadge) totalBadge.textContent = `${allContacts.length.toLocaleString()} contacts`;

        const relCounts = { All: allContacts.length, Customer: 0, Prospect: 0, Partner: 0, Inactive: 0 };
        allContacts.forEach(c => {
            if (!c || !c.relationship) return;
            const rel = c.relationship.toString().trim().toLowerCase();
            if (rel === "customer") relCounts.Customer++;
            else if (rel === "prospect") relCounts.Prospect++;
            else if (rel === "partner") relCounts.Partner++;
            else if (rel === "inactive") relCounts.Inactive++;
        });

        for (const [rel, count] of Object.entries(relCounts)) {
            const countEl = document.getElementById(`tabCount_${rel}`);
            if (countEl) countEl.textContent = count;
        }
    }

    function getPagedContacts() {
        const start = (currentPage - 1) * rowsPerPage;
        return filteredContacts.slice(start, start + rowsPerPage);
    }

    let activeTargetContact = null;
    let activeModalId = null;

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove("show");
        }
        if (activeModalId === modalId) {
            activeModalId = null;
        }
        // Restore drawer scroll position after child modal closes
        const drawerBody = document.querySelector(".contact-view-body");
        if (drawerBody && window._crmDrawerScrollBeforeModal !== undefined) {
            drawerBody.scrollTop = window._crmDrawerScrollBeforeModal;
        }
        restoreKeyboardFocus();
    }

    function openModal(modalId) {
        document.querySelectorAll(".dropdown-menu.row-menu.show").forEach(m => m.classList.remove("show"));

        if (activeModalId && activeModalId !== modalId) {
            closeModal(activeModalId);
        }

        // Save drawer scroll position before opening child modal
        const drawerBody = document.querySelector(".contact-view-body");
        if (drawerBody) {
            window._crmDrawerScrollBeforeModal = drawerBody.scrollTop;
        }

        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add("show");
            activeModalId = modalId;
            const firstFocus = modal.querySelector("input:not([readonly]), select, textarea, button");
            if (firstFocus && typeof firstFocus.focus === "function") {
                setTimeout(() => firstFocus.focus(), 50);
            }
        }
    }

    function showToast(message, actionLabel, actionCallback) {
        const existing = document.getElementById("crmGlobalToast");
        if (existing) existing.remove();

        const toast = document.createElement("div");
        toast.id = "crmGlobalToast";
        toast.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            background-color: #1E293B;
            color: #FFFFFF;
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
            z-index: 2000;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: toastFadeIn 0.25s ease-out;
        `;

        let html = `<span>${escapeHtml(message)}</span>`;
        if (actionLabel && typeof actionCallback === "function") {
            html += `<button type="button" id="toastUndoBtn" style="background: none; border: none; color: #38BDF8; font-weight: 600; font-size: 13px; cursor: pointer; text-decoration: underline; padding: 0;">${escapeHtml(actionLabel)}</button>`;
        }
        toast.innerHTML = html;
        document.body.appendChild(toast);

        if (actionLabel && typeof actionCallback === "function") {
            const undoBtn = toast.querySelector("#toastUndoBtn");
            if (undoBtn) {
                undoBtn.addEventListener("click", function() {
                    actionCallback();
                    toast.remove();
                });
            }
        }

        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.style.opacity = "0";
                toast.style.transition = "opacity 0.3s ease";
                setTimeout(() => toast.remove(), 300);
            }
        }, actionLabel ? 8000 : 3000);
    }

    function saveEditContactSubmit() {
        if (!activeTargetContact) return;
        const firstName = document.getElementById("editFirstName").value.trim();
        const lastName = document.getElementById("editLastName").value.trim();
        const company = document.getElementById("editCompany").value.trim();
        const email = document.getElementById("editEmail").value.trim();

        if (!firstName || !lastName || !company || !email) {
            alert("Please fill in all required fields (First Name, Last Name, Company, Work Email).");
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            alert("Please enter a valid work email address.");
            return;
        }

        const fullName = `${firstName} ${lastName}`;
        const newRel = document.getElementById("editRelationship").value;

        activeTargetContact.name = fullName;
        activeTargetContact.title = document.getElementById("editTitle").value.trim() || activeTargetContact.title;
        activeTargetContact.company = company;
        activeTargetContact.industry = document.getElementById("editIndustry").value.trim() || activeTargetContact.industry;
        activeTargetContact.email = email;
        activeTargetContact.phone = document.getElementById("editPhone").value.trim() || activeTargetContact.phone;
        activeTargetContact.relationship = newRel;

        const ownerName = document.getElementById("editOwner").value;
        const ownerMap = {
            "Sarah Chen": { initials: "SC", color: "#7C3AED" },
            "James Wu": { initials: "JW", color: "#0284C7" },
            "Olivia Martin": { initials: "OM", color: "#059669" }
        };
        const meta = ownerMap[ownerName] || { initials: "SC", color: "#7C3AED" };
        activeTargetContact.owner = ownerName;
        activeTargetContact.ownerInitials = meta.initials;
        activeTargetContact.ownerColor = meta.color;
        activeTargetContact.location = document.getElementById("editLocation").value.trim() || activeTargetContact.location;
        activeTargetContact.linkedin = document.getElementById("editLinkedIn").value.trim() || activeTargetContact.linkedin;
        activeTargetContact.preferredChannel = document.getElementById("editPrefChannel").value;
        activeTargetContact.notes = document.getElementById("editNotes").value.trim();

        const custom = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
        const idx = custom.findIndex(item => item.id === activeTargetContact.id);
        if (idx >= 0) custom[idx] = activeTargetContact;
        else custom.unshift(activeTargetContact);
        localStorage.setItem(STORAGE_KEY, JSON.stringify(custom));

        applyFilterAndSort();

        if (activeContactForDetails && activeContactForDetails.id === activeTargetContact.id) {
            window.contactsApp.openDetails(activeTargetContact.id);
        }

        closeModal("editContactModal");
        showToast("Contact updated successfully.");
    }

    function saveTaskFormSubmit() {
        if (!activeTargetContact) return;
        const title = document.getElementById("taskTitleInput").value.trim();
        if (!title) {
            alert("Please enter a task title.");
            return;
        }

        const type = document.getElementById("taskTypeSelect").value;
        const priority = document.getElementById("taskPrioritySelect").value;
        const dueDate = document.getElementById("taskDueDateInput").value || "Tomorrow";
        const dueTime = document.getElementById("taskDueTimeInput").value || "10:00";
        const assignee = document.getElementById("taskOwnerSelect").value;
        const description = document.getElementById("taskNotesInput").value.trim();

        const newTask = {
            id: `T-${Date.now().toString().slice(-4)}`,
            title: title,
            type: type,
            priority: priority,
            dueDate: `${dueDate} ${dueTime}`,
            assignee: assignee,
            description: description,
            status: "Pending"
        };

        if (!contactTasksStore[activeTargetContact.id]) {
            contactTasksStore[activeTargetContact.id] = [];
        }
        contactTasksStore[activeTargetContact.id].unshift(newTask);
        localStorage.setItem(TASKS_KEY, JSON.stringify(contactTasksStore));

        if (activeContactForDetails && activeContactForDetails.id === activeTargetContact.id && activeTabName === "tasks") {
            renderTabPanelContent("tasks", document.getElementById("cdTabContent"), activeContactForDetails);
        }

        closeModal("contactAddTaskModal");
        showToast("Task created successfully.");
    }

    function saveNoteFormSubmit() {
        if (!activeTargetContact) return;
        const text = document.getElementById("noteTextInput").value.trim();
        if (!text) {
            alert("Please enter note content.");
            return;
        }

        const pinned = document.getElementById("notePinCheckbox").checked;
        const category = document.getElementById("noteCategorySelect").value;

        const newNote = {
            id: `N-${Date.now().toString().slice(-4)}`,
            text: text,
            pinned: pinned,
            category: category,
            author: "Olivia Martin",
            authorInitials: "OM",
            date: "Just now"
        };

        if (!contactNotesStore[activeTargetContact.id]) {
            contactNotesStore[activeTargetContact.id] = [];
        }

        if (pinned) {
            contactNotesStore[activeTargetContact.id].unshift(newNote);
        } else {
            contactNotesStore[activeTargetContact.id].push(newNote);
        }

        localStorage.setItem(NOTES_KEY, JSON.stringify(contactNotesStore));

        if (activeContactForDetails && activeContactForDetails.id === activeTargetContact.id && activeTabName === "notes") {
            renderTabPanelContent("notes", document.getElementById("cdTabContent"), activeContactForDetails);
        }

        closeModal("contactAddNoteModal");
        showToast("Note saved successfully.");
    }

    function renderContacts() {
        if (viewMode === "list") {
            document.getElementById("contactsListView").style.display = "block";
            document.getElementById("contactsGridView").style.display = "none";
            renderTableRows();
        } else {
            document.getElementById("contactsListView").style.display = "none";
            document.getElementById("contactsGridView").style.display = "grid";
            renderGridCards();
        }
        renderPagination();
    }

    function renderTableRows() {
        const tbody = document.getElementById("contactsTableBody");
        if (!tbody) return;

        const paged = getPagedContacts();

        if (paged.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                        No contacts found matching your search or filters.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = paged.map(c => {
            const isSelected = selectedContactIds.has(c.id);
            const relClass = (c.relationship || "Customer").toLowerCase();
            const relText = c.relationship || "Customer";
            const initials = (c.name || "?").split(" ").map(n => n[0]).join("").toUpperCase();
            const isInactive = relClass === "inactive";

            return `
                <tr class="${isSelected ? 'selected' : ''}" data-id="${c.id}" data-relationship="${relClass}">
                    <td style="text-align: center;">
                        <div class="custom-checkbox row-checkbox ${isSelected ? 'checked' : ''}" data-id="${c.id}"></div>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="avatar avatar-sm" style="background-color: ${c.ownerColor || '#7C3AED'};">${initials}</div>
                            <div>
                                <div style="font-weight: 600; color: var(--text-heading);">${escapeHtml(c.name)}</div>
                                <div style="font-size: 12px; color: var(--text-muted);">${escapeHtml(c.title || 'Contact')}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="color: var(--text-body); font-weight: 500;">${escapeHtml(c.company || '')}</div>
                        <div style="font-size: 12px; color: var(--text-muted);">${escapeHtml(c.industry || '')}</div>
                    </td>
                    <td>
                        <span class="status-badge status-${relClass}">
                            <span class="status-dot"></span>
                            ${escapeHtml(relText)}
                        </span>
                    </td>
                    <td>
                        <div style="font-size: 13px; color: var(--text-heading); font-weight: 500; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(c.email || '')}">${escapeHtml(c.email || '')}</div>
                        <div style="font-size: 12px; color: var(--text-muted);">${escapeHtml(c.phone || '')}</div>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <div class="avatar avatar-xs" style="background-color: ${c.ownerColor || '#7C3AED'}; font-size: 10px;">
                                ${escapeHtml(c.ownerInitials || 'SC')}
                            </div>
                            <span style="font-size: 12px; color: var(--text-secondary);">${escapeHtml(c.owner || 'Sarah Chen')}</span>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: var(--text-body);">${c.openDealsCount || 0} deals</div>
                        <div style="font-size: 12px; color: var(--text-muted); font-variant-numeric: tabular-nums;">$${(c.openDealsValue || 0).toLocaleString()}</div>
                    </td>
                    <td>
                        <div style="font-size: 13px; color: var(--text-body);">${escapeHtml(c.lastInteraction || 'N/A')}</div>
                        <div style="font-size: 12px; color: var(--text-muted);">${escapeHtml(c.lastInteractionType || '')}</div>
                    </td>
                    <td style="white-space: nowrap; text-align: center; position: relative;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 4px;">
                            <button class="btn btn-ghost btn-xs" title="Call Contact" style="padding: 4px; color: #12B76A;" onclick="event.stopPropagation(); triggerContactsCall('${c.id}')">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                            </button>
                            <button class="btn btn-ghost btn-xs" title="WhatsApp Message" style="padding: 4px; color: #25D366;" onclick="event.stopPropagation(); triggerContactsWhatsApp('${c.id}')">
                                <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            </button>
                            <button class="btn btn-ghost btn-xs" title="Send Email" style="padding: 4px; color: #2563EB;" onclick="event.stopPropagation(); triggerContactsEmail('${c.id}')">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            </button>
                            <button type="button" class="btn btn-ghost btn-xs row-actions-btn contact-actions-trigger" title="More Actions" aria-haspopup="menu" aria-expanded="false" style="padding: 4px;" data-contact-menu-trigger data-contact-id="${c.id}" aria-label="Open actions for ${escapeHtml(c.name)}">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join("");
    }

    function renderGridCards() {
        const grid = document.getElementById("contactsGridView");
        if (!grid) return;

        const paged = getPagedContacts();

        if (paged.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-muted);">No contacts found.</div>';
            return;
        }

        grid.innerHTML = paged.map(c => {
            const relSlug = (c.relationship || 'Customer').toLowerCase();
            const initials = (c.name || "?").split(" ").map(n => n[0]).join("").toUpperCase();

            return `
                <div class="contacts-card">
                    <div class="contacts-card-top">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="avatar avatar-md" style="background-color: ${c.ownerColor || '#7C3AED'}; flex-shrink: 0;">${initials}</div>
                            <div>
                                <div style="font-size: 14px; font-weight: 600; color: var(--text-heading);">${escapeHtml(c.name)}</div>
                                <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(c.title || 'Contact')}</div>
                            </div>
                        </div>
                        <span class="status-badge status-${relSlug}">${escapeHtml(c.relationship)}</span>
                    </div>
                    <div class="contacts-card-info">
                        <div class="contacts-card-row"><span>Company:</span> <strong style="color: var(--text-heading);">${escapeHtml(c.company)}</strong></div>
                        <div class="contacts-card-row"><span>Email:</span> <span>${escapeHtml(c.email)}</span></div>
                        <div class="contacts-card-row"><span>Phone:</span> <span>${escapeHtml(c.phone)}</span></div>
                        <div class="contacts-card-row"><span>Deals:</span> <strong>${c.openDealsCount || 0} ($${(c.openDealsValue || 0).toLocaleString()})</strong></div>
                    </div>
                    <div class="contacts-card-footer">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <div class="avatar avatar-xs" style="background-color: ${c.ownerColor || '#0284C7'};">${c.ownerInitials || 'SC'}</div>
                            <span style="font-size: 11px; color: var(--text-muted);">${escapeHtml(c.owner)}</span>
                        </div>
                        <div class="contacts-comm-actions">
                            <button type="button" class="contacts-comm-btn call" title="Call" onclick="window.contactsApp.triggerCall('${c.id}', this)"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg></button>
                            <button type="button" class="contacts-comm-btn wa" title="WhatsApp" onclick="window.contactsApp.triggerWA('${c.id}', this)"><svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg></button>
                            <button type="button" class="contacts-comm-btn email" title="Email" onclick="window.contactsApp.triggerEmail('${c.id}', this)"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></button>
                        </div>
                    </div>
                </div>
            `;
        }).join("");
    }

    function renderPagination() {
        const total = filteredContacts.length;
        const start = total === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
        const end = Math.min(currentPage * rowsPerPage, total);

        const pagingRange = document.getElementById("pagingRange");
        const pagingTotal = document.getElementById("pagingTotal");
        if (pagingRange) pagingRange.textContent = total === 0 ? "0" : `${start}–${end}`;
        if (pagingTotal) pagingTotal.textContent = total;

        const totalPages = Math.ceil(total / rowsPerPage) || 1;
        const controls = document.getElementById("contactsPaginationControls");
        if (!controls) return;

        let html = `
            <button type="button" class="pagination-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="window.contactsApp.changePage(-1)">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
        `;

        for (let i = 1; i <= totalPages; i++) {
            html += `<button type="button" class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="window.contactsApp.goToPage(${i})">${i}</button>`;
        }

        html += `
            <button type="button" class="pagination-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="window.contactsApp.changePage(1)">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        `;

        controls.innerHTML = html;
    }

    function updateBulkToolbarState() {
        const toolbar = document.getElementById("contactsBulkToolbar");
        const countText = document.getElementById("contactsSelectedCountText");

        if (selectedContactIds.size > 0) {
            if (toolbar) toolbar.style.display = "flex";
            if (countText) countText.textContent = selectedContactIds.size;
        } else {
            if (toolbar) toolbar.style.display = "none";
            const selectAll = document.getElementById("contactsSelectAll");
            if (selectAll) selectAll.classList.remove("checked");
        }
    }

    function restoreKeyboardFocus() {
        if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
            lastFocusedElement.focus();
            lastFocusedElement = null;
        }
    }

    // Public Contacts API
    window.contactsApp = {
        filterByTab: function(rel, elem) {
            currentRelationship = rel;
            document.querySelectorAll("#contactsStatusFilterTabs .filter-chip").forEach(c => c.classList.remove("active"));
            if (elem) elem.classList.add("active");
            currentPage = 1;
            applyFilterAndSort();

            const tableScroll = document.getElementById("contactsTableScroll") || document.querySelector(".crm-table-wrapper");
            if (tableScroll) tableScroll.scrollLeft = 0;
        },

        setViewMode: function(mode) {
            viewMode = mode;
            document.getElementById("btnViewList").classList.toggle("active", mode === "list");
            document.getElementById("btnViewGrid").classList.toggle("active", mode === "grid");
            renderContacts();

            if (mode === "list") {
                const tableScroll = document.getElementById("contactsTableScroll") || document.querySelector(".crm-table-wrapper");
                if (tableScroll) tableScroll.scrollLeft = 0;
            }
        },

        toggleRowSelection: function(id, isChecked) {
            if (isChecked) {
                selectedContactIds.add(id);
            } else {
                selectedContactIds.delete(id);
            }
            updateBulkToolbarState();
        },

        executeBulkAction: function(actionName) {
            const count = selectedContactIds.size;
            if (count === 0) return;

            if (actionName === "delete") {
                if (confirm(`Are you sure you want to delete ${count} selected contacts?`)) {
                    allContacts = allContacts.filter(c => !selectedContactIds.has(c.id));
                    selectedContactIds.clear();
                    updateBulkToolbarState();
                    applyFilterAndSort();
                    showToast(`${count} contacts deleted.`);
                }
            } else {
                showToast(`Bulk action '${actionName}' executed on ${count} contacts.`);
                selectedContactIds.clear();
                updateBulkToolbarState();
                renderTableRows();
            }
        },

        goToPage: function(p) {
            currentPage = p;
            renderContacts();
        },

        changePage: function(delta) {
            currentPage += delta;
            renderContacts();
        },

        openDetails: function(id, triggerElement) {
            if (triggerElement) {
                lastFocusedElement = triggerElement;
            } else {
                lastFocusedElement = document.activeElement;
            }

            const c = allContacts.find(item => item.id === id);
            if (!c) {
                showToast("Unable to load this contact.");
                return;
            }

            activeContactForDetails = c;

            const drawer = document.getElementById("contactsDetailsDrawer");
            const avatar = document.getElementById("cdAvatar");
            const name = document.getElementById("cdName");
            const breadcrumbName = document.getElementById("cdBreadcrumbName");
            const titleComp = document.getElementById("cdTitleCompany");
            const relBadge = document.getElementById("cdRelBadge");

            if (avatar) {
                avatar.textContent = (c.name || "?").split(" ").map(n => n[0]).join("").toUpperCase();
                avatar.style.backgroundColor = c.ownerColor || "#7C3AED";
            }
            if (name) name.textContent = c.name;
            if (breadcrumbName) breadcrumbName.textContent = c.name;
            if (titleComp) titleComp.textContent = `${c.title || 'Contact'} • ${c.company}`;
            if (relBadge) {
                relBadge.textContent = c.relationship;
                relBadge.className = `status-badge status-${c.relationship.toLowerCase()}`;
            }

            const ownerEl = document.getElementById("cdOwner");
            const locEl = document.getElementById("cdLocation");
            if (ownerEl) ownerEl.textContent = c.owner;
            if (locEl) locEl.textContent = c.location || "San Francisco, CA";

            window.contactsApp.switchDetailsTab("overview");

            if (drawer) {
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
                const panel = drawer.querySelector(".contact-view-panel");
                if (panel) panel.setAttribute("aria-hidden", "false");
            }

            document.body.style.overflow = "hidden";
        },

        closeDetails: function() {
            const drawer = document.getElementById("contactsDetailsDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
                const panel = drawer.querySelector(".contact-view-panel");
                if (panel) panel.setAttribute("aria-hidden", "true");
            }
            document.body.style.overflow = "";
            window.contactsApp.closeMoreDropdown();
            restoreKeyboardFocus();
        },

        switchDetailsTab: function(tabName) {
            activeTabName = tabName.toLowerCase();

            const tabs = document.querySelectorAll(".contact-view-tabs [role='tab']");
            tabs.forEach(t => {
                const isSelected = t.id === `tab-${activeTabName}`;
                t.classList.toggle("active", isSelected);
                t.setAttribute("aria-selected", isSelected ? "true" : "false");
                t.setAttribute("tabindex", isSelected ? "0" : "-1");
            });

            const container = document.getElementById("cdTabContent");
            if (!container || !activeContactForDetails) return;

            const drawerBody = document.querySelector(".contact-view-body");
            if (drawerBody) drawerBody.scrollTop = 0;

            renderTabPanelContent(activeTabName, container, activeContactForDetails);
        },

        toggleMoreDropdown: function() {
            const menu = document.getElementById("cdMoreMenu");
            if (menu) menu.classList.toggle("show");
        },

        closeMoreDropdown: function() {
            const menu = document.getElementById("cdMoreMenu");
            if (menu) menu.classList.remove("show");
        },

        closeAddTaskModal: function() {
            closeModal("contactAddTaskModal");
        },

        // --- Action Modal Methods ---
        closeModal: function(modalId) {
            closeModal(modalId);
        },

        openEditModal: function(id) {
            lastFocusedElement = document.activeElement;
            const contact = allContacts.find(c => c.id === id);
            if (!contact) return;
            activeTargetContact = contact;

            const names = (contact.name || "").split(" ");
            const firstName = names[0] || "";
            const lastName = names.slice(1).join(" ") || "";

            document.getElementById("editContactTargetId").value = contact.id;
            document.getElementById("editFirstName").value = firstName;
            document.getElementById("editLastName").value = lastName;
            document.getElementById("editTitle").value = contact.title || "";
            document.getElementById("editCompany").value = contact.company || "";
            document.getElementById("editIndustry").value = contact.industry || "";
            document.getElementById("editEmail").value = contact.email || "";
            document.getElementById("editPhone").value = contact.phone || "";
            document.getElementById("editWhatsApp").value = contact.phone || "";
            document.getElementById("editRelationship").value = contact.relationship || "Customer";
            document.getElementById("editOwner").value = contact.owner || "Sarah Chen";
            document.getElementById("editLocation").value = contact.location || "";
            document.getElementById("editLinkedIn").value = contact.linkedin || "";
            document.getElementById("editPrefChannel").value = contact.preferredChannel || "Email";
            document.getElementById("editNotes").value = contact.notes || "";

            openModal("editContactModal");
        },

        openEditFromDetails: function() {
            if (!activeContactForDetails) return;
            window.contactsApp.closeMoreDropdown();
            window.contactsApp.openEditModal(activeContactForDetails.id);
        },

        openAddTaskModal: function(id) {
            lastFocusedElement = document.activeElement;
            const contact = allContacts.find(c => c.id === id);
            if (!contact) return;
            activeTargetContact = contact;

            const initials = contact.name.split(" ").map(n => n[0]).join("").toUpperCase();
            document.getElementById("taskContactAvatar").textContent = initials;
            document.getElementById("taskContactAvatar").style.backgroundColor = contact.ownerColor || "#7C3AED";
            document.getElementById("taskContactName").textContent = contact.name;
            document.getElementById("taskContactCompany").textContent = contact.company;

            document.getElementById("taskTitleInput").value = "";
            document.getElementById("taskTypeSelect").value = "General";
            document.getElementById("taskPrioritySelect").value = "Medium";
            
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById("taskDueDateInput").value = tomorrow.toISOString().split("T")[0];
            document.getElementById("taskDueTimeInput").value = "10:00";
            document.getElementById("taskOwnerSelect").value = contact.owner || "Olivia Martin";
            document.getElementById("taskRelatedDealInput").value = `${contact.company} Deal`;
            document.getElementById("taskNotesInput").value = "";

            openModal("contactAddTaskModal");
        },

        openTaskFromDetails: function() {
            if (!activeContactForDetails) return;
            window.contactsApp.closeMoreDropdown();
            window.contactsApp.openAddTaskModal(activeContactForDetails.id);
        },

        openAddNoteModal: function(id) {
            lastFocusedElement = document.activeElement;
            const contact = allContacts.find(c => c.id === id);
            if (!contact) return;
            activeTargetContact = contact;

            document.getElementById("noteContactName").textContent = contact.name;
            document.getElementById("noteContactCompany").textContent = contact.company;
            document.getElementById("noteTextInput").value = "";
            document.getElementById("notePinCheckbox").checked = false;
            document.getElementById("noteCategorySelect").value = "General";

            openModal("contactAddNoteModal");
        },

        openNoteFromDetails: function() {
            if (!activeContactForDetails) return;
            window.contactsApp.closeMoreDropdown();
            window.contactsApp.openAddNoteModal(activeContactForDetails.id);
        },

        openChangeOwnerModal: function(id) {
            lastFocusedElement = document.activeElement;
            const contact = allContacts.find(c => c.id === id);
            if (!contact) return;
            activeTargetContact = contact;

            document.getElementById("ownerContactName").textContent = contact.name;
            document.getElementById("ownerCurrentText").textContent = contact.owner || "Sarah Chen";

            const owners = [
                { name: "Sarah Chen", role: "Sales Executive", color: "#7C3AED", initials: "SC" },
                { name: "James Wu", role: "Account Executive", color: "#0284C7", initials: "JW" },
                { name: "Olivia Martin", role: "Sales Manager", color: "#059669", initials: "OM" }
            ];

            const container = document.getElementById("ownerOptionsList");
            container.innerHTML = owners.map(o => {
                const isCurrent = (contact.owner || "Sarah Chen") === o.name;
                return `
                    <label style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-radius: var(--radius-md); border: 1px solid ${isCurrent ? 'var(--primary)' : 'var(--border-divider)'}; background-color: ${isCurrent ? '#F0F5FF' : '#FFFFFF'}; cursor: pointer;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="radio" name="ownerChoiceRadio" value="${o.name}" ${isCurrent ? 'checked' : ''} style="accent-color: var(--primary);">
                            <div class="avatar avatar-sm" style="background-color: ${o.color}; font-size: 11px;">${o.initials}</div>
                            <div>
                                <div style="font-size: 13px; font-weight: 600; color: var(--text-heading);">${o.name}</div>
                                <div style="font-size: 11px; color: var(--text-muted);">${o.role}</div>
                            </div>
                        </div>
                        ${isCurrent ? '<span style="font-size: 11px; font-weight: 600; color: var(--primary); background-color: rgba(37, 99, 235, 0.1); padding: 2px 8px; border-radius: 999px;">Current</span>' : ''}
                    </label>
                `;
            }).join("");

            openModal("contactChangeOwnerModal");
        },

        confirmChangeOwner: function() {
            if (!activeTargetContact) return;
            const selectedRadio = document.querySelector("input[name='ownerChoiceRadio']:checked");
            if (!selectedRadio) return;

            const newOwnerName = selectedRadio.value;
            const ownerMap = {
                "Sarah Chen": { initials: "SC", color: "#7C3AED" },
                "James Wu": { initials: "JW", color: "#0284C7" },
                "Olivia Martin": { initials: "OM", color: "#059669" }
            };

            const meta = ownerMap[newOwnerName] || { initials: "SC", color: "#7C3AED" };
            activeTargetContact.owner = newOwnerName;
            activeTargetContact.ownerInitials = meta.initials;
            activeTargetContact.ownerColor = meta.color;

            applyFilterAndSort();

            if (activeContactForDetails && activeContactForDetails.id === activeTargetContact.id) {
                const ownerEl = document.getElementById("cdOwner");
                if (ownerEl) ownerEl.textContent = newOwnerName;
            }

            closeModal("contactChangeOwnerModal");
            showToast(`${activeTargetContact.name} assigned to ${newOwnerName}`);
        },

        openOwnerFromDetails: function() {
            if (!activeContactForDetails) return;
            window.contactsApp.closeMoreDropdown();
            window.contactsApp.openChangeOwnerModal(activeContactForDetails.id);
        },

        openChangeRelationshipModal: function(id) {
            lastFocusedElement = document.activeElement;
            const contact = allContacts.find(c => c.id === id);
            if (!contact) return;
            activeTargetContact = contact;

            document.getElementById("relContactName").textContent = contact.name;
            document.getElementById("relCurrentText").textContent = contact.relationship || "Customer";
            document.getElementById("relSelectOption").value = contact.relationship || "Customer";
            document.getElementById("relReasonInput").value = "";

            openModal("contactChangeRelationshipModal");
        },

        confirmChangeRelationship: function() {
            if (!activeTargetContact) return;
            const newRel = document.getElementById("relSelectOption").value;
            activeTargetContact.relationship = newRel;

            applyFilterAndSort();

            if (activeContactForDetails && activeContactForDetails.id === activeTargetContact.id) {
                const badge = document.getElementById("cdRelBadge");
                if (badge) {
                    badge.className = `status-badge status-${newRel.toLowerCase()}`;
                    badge.textContent = newRel;
                }
            }

            closeModal("contactChangeRelationshipModal");
            showToast("Relationship updated successfully.");
        },

        openRelationshipFromDetails: function() {
            if (!activeContactForDetails) return;
            window.contactsApp.closeMoreDropdown();
            window.contactsApp.openChangeRelationshipModal(activeContactForDetails.id);
        },

        openMarkInactiveModal: function(id) {
            lastFocusedElement = document.activeElement;
            const contact = allContacts.find(c => c.id === id);
            if (!contact) return;
            activeTargetContact = contact;

            document.getElementById("inactiveContactName").textContent = contact.name;
            document.getElementById("inactiveContactCompany").textContent = contact.company;

            openModal("contactMarkInactiveModal");
        },

        confirmMarkInactive: function() {
            if (!activeTargetContact) return;
            const previousRel = activeTargetContact.relationship;
            const targetContact = activeTargetContact;
            targetContact.relationship = "Inactive";

            applyFilterAndSort();

            if (activeContactForDetails && activeContactForDetails.id === targetContact.id) {
                const badge = document.getElementById("cdRelBadge");
                if (badge) {
                    badge.className = "status-badge status-inactive";
                    badge.textContent = "Inactive";
                }
            }

            closeModal("contactMarkInactiveModal");

            showToast(`Marked ${targetContact.name} as Inactive`, "Undo", function() {
                targetContact.relationship = previousRel;
                applyFilterAndSort();
                if (activeContactForDetails && activeContactForDetails.id === targetContact.id) {
                    const badge = document.getElementById("cdRelBadge");
                    if (badge) {
                        badge.className = `status-badge status-${previousRel.toLowerCase()}`;
                        badge.textContent = previousRel;
                    }
                }
                showToast("Restored relationship status.");
            });
        },

        openInactiveFromDetails: function() {
            if (!activeContactForDetails) return;
            window.contactsApp.closeMoreDropdown();
            if (activeContactForDetails.relationship === "Inactive") {
                window.contactsApp.openReactivateModal(activeContactForDetails.id);
            } else {
                window.contactsApp.openMarkInactiveModal(activeContactForDetails.id);
            }
        },

        openReactivateModal: function(id) {
            lastFocusedElement = document.activeElement;
            const contact = allContacts.find(c => c.id === id);
            if (!contact) return;
            activeTargetContact = contact;

            document.getElementById("reactivateContactName").textContent = contact.name;
            document.getElementById("reactivateRelSelect").value = "Customer";

            openModal("contactReactivateModal");
        },

        confirmReactivate: function() {
            if (!activeTargetContact) return;
            const restoredRel = document.getElementById("reactivateRelSelect").value;
            activeTargetContact.relationship = restoredRel;

            applyFilterAndSort();

            if (activeContactForDetails && activeContactForDetails.id === activeTargetContact.id) {
                const badge = document.getElementById("cdRelBadge");
                if (badge) {
                    badge.className = `status-badge status-${restoredRel.toLowerCase()}`;
                    badge.textContent = restoredRel;
                }
            }

            closeModal("contactReactivateModal");
            showToast(`Contact reactivated as ${restoredRel}`);
        },

        openDeleteContactModal: function(id) {
            lastFocusedElement = document.activeElement;
            const contact = allContacts.find(c => c.id === id);
            if (!contact) return;
            activeTargetContact = contact;

            const initials = contact.name.split(" ").map(n => n[0]).join("").toUpperCase();
            document.getElementById("deleteContactAvatar").textContent = initials;
            document.getElementById("deleteContactAvatar").style.backgroundColor = contact.ownerColor || "#7C3AED";
            document.getElementById("deleteContactName").textContent = contact.name;
            document.getElementById("deleteContactCompany").textContent = contact.company;
            document.getElementById("deleteContactDeals").textContent = `${contact.openDealsCount || 0} deals`;
            const tasksCount = (contactTasksStore[contact.id] || []).length;
            document.getElementById("deleteContactTasks").textContent = `${tasksCount} tasks`;

            openModal("contactDeleteModal");
        },

        confirmDeleteContact: function() {
            if (!activeTargetContact) return;
            const targetId = activeTargetContact.id;
            const originalIndex = allContacts.findIndex(c => c.id === targetId);
            const deletedContact = activeTargetContact;

            allContacts = allContacts.filter(c => c.id !== targetId);
            selectedContactIds.delete(targetId);

            applyFilterAndSort();

            if (activeContactForDetails && activeContactForDetails.id === targetId) {
                window.contactsApp.closeDetails();
            }

            closeModal("contactDeleteModal");

            showToast(`Deleted contact '${deletedContact.name}'`, "Undo", function() {
                if (originalIndex >= 0 && originalIndex <= allContacts.length) {
                    allContacts.splice(originalIndex, 0, deletedContact);
                } else {
                    allContacts.unshift(deletedContact);
                }
                applyFilterAndSort();
                showToast(`Restored contact '${deletedContact.name}'`);
            });
        },

        openDeleteFromDetails: function() {
            if (!activeContactForDetails) return;
            window.contactsApp.closeMoreDropdown();
            window.contactsApp.openDeleteContactModal(activeContactForDetails.id);
        },

        openAddDrawer: function() {
            lastFocusedElement = document.activeElement;
            const form = document.getElementById("addContactForm");
            if (form) form.reset();
            document.getElementById("editContactId").value = "";
            document.getElementById("addContactModalTitle").textContent = "Add New Contact";
            document.getElementById("btnSaveContactSubmit").textContent = "Save Contact";

            const drawer = document.getElementById("contactsAddDrawer");
            if (drawer) drawer.classList.add("show");
        },

        closeAddDrawer: function() {
            const drawer = document.getElementById("contactsAddDrawer");
            if (drawer) drawer.classList.remove("show");
            restoreKeyboardFocus();
        },

        openFilterDrawer: function() {
            const panel = document.getElementById("contactsFilterDrawer");
            if (panel) panel.classList.add("show");
        },

        closeFilterDrawer: function() {
            const panel = document.getElementById("contactsFilterDrawer");
            if (panel) panel.classList.remove("show");
        },

        copyToClipboard: function(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast(`Copied '${text}' to clipboard!`);
            }).catch(() => {
                showContactsToast(`Copied: ${text}`);
            });
        },

        triggerCall: function(id, btnElem) {
            commTriggerElement = btnElem || document.activeElement;
            const c = allContacts.find(item => item.id === id) || activeContactForDetails;
            if (!c || !c.phone) {
                showContactsToast("Phone number unavailable.");
                return;
            }
            if (window.leadComm && typeof window.leadComm.openCallChoice === "function") {
                window.leadComm.openCallChoice(c.id);
            } else if (window.softphone) {
                window.softphone.dialLead(c.id);
            }
        },

        triggerWA: function(id, btnElem) {
            commTriggerElement = btnElem || document.activeElement;
            const c = allContacts.find(item => item.id === id) || activeContactForDetails;
            if (!c || !c.phone) {
                showContactsToast("WhatsApp number unavailable.");
                return;
            }
            if (window.leadComm && typeof window.leadComm.openWhatsApp === "function") {
                window.leadComm.openWhatsApp(c.id);
            }
        },

        triggerEmail: function(id, btnElem) {
            commTriggerElement = btnElem || document.activeElement;
            const c = allContacts.find(item => item.id === id) || activeContactForDetails;
            if (!c || !c.email || !c.email.includes("@")) {
                showContactsToast("Email address unavailable.");
                return;
            }
            if (window.leadComm && typeof window.leadComm.openEmail === "function") {
                window.leadComm.openEmail(c.id);
            }
        },

        // Open Log Activity modal — uses activeContactForDetails when opened from drawer tab
        openLogActivityModal: function(id) {
            lastFocusedElement = document.activeElement;
            const contact = id
                ? allContacts.find(c => c.id === id)
                : activeContactForDetails;
            if (!contact) return;
            activeTargetContact = contact;

            const initials = contact.name.split(" ").map(n => n[0]).join("").toUpperCase();
            const av = document.getElementById("activityContactAvatar");
            if (av) { av.textContent = initials; av.style.backgroundColor = contact.ownerColor || "#7C3AED"; }
            const nameEl = document.getElementById("activityContactName");
            if (nameEl) nameEl.textContent = contact.name;
            const compEl = document.getElementById("activityContactCompany");
            if (compEl) compEl.textContent = contact.company;

            // Default date/time to now
            const now = new Date();
            const pad = n => String(n).padStart(2, "0");
            const todayStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
            const timeStr = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
            const dateEl = document.getElementById("activityDateInput");
            if (dateEl) dateEl.value = todayStr;
            const timeEl = document.getElementById("activityTimeInput");
            if (timeEl) timeEl.value = timeStr;

            // Reset other fields
            const titleEl = document.getElementById("activityTitleInput");
            if (titleEl) titleEl.value = "";
            const descEl = document.getElementById("activityDescInput");
            if (descEl) descEl.value = "";
            const followupEl = document.getElementById("activityFollowupInput");
            if (followupEl) followupEl.value = "";
            const typeEl = document.getElementById("activityTypeSelect");
            if (typeEl) typeEl.value = "Call";
            const outcomeEl = document.getElementById("activityOutcomeSelect");
            if (outcomeEl) outcomeEl.value = "Completed";

            openModal("logActivityModal");
        },

        // Open Create Deal modal — uses activeContactForDetails when opened from drawer tab
        openCreateDealModal: function(id) {
            lastFocusedElement = document.activeElement;
            const contact = id
                ? allContacts.find(c => c.id === id)
                : activeContactForDetails;
            if (!contact) return;
            activeTargetContact = contact;

            const contactEl = document.getElementById("dealContactInput");
            if (contactEl) contactEl.value = contact.name;
            const compEl = document.getElementById("dealCompanyInput");
            if (compEl) compEl.value = contact.company;

            // Suggest a deal name
            const nameEl = document.getElementById("dealNameInput");
            if (nameEl) nameEl.value = `${contact.company} Deal`;

            // Default close date 30 days out
            const future = new Date();
            future.setDate(future.getDate() + 30);
            const pad = n => String(n).padStart(2, "0");
            const closeDateStr = `${future.getFullYear()}-${pad(future.getMonth() + 1)}-${pad(future.getDate())}`;
            const closeDateEl = document.getElementById("dealCloseDateInput");
            if (closeDateEl) closeDateEl.value = closeDateStr;

            // Reset
            const valueEl = document.getElementById("dealValueInput");
            if (valueEl) valueEl.value = "";
            const stageEl = document.getElementById("dealStageSelect");
            if (stageEl) stageEl.value = "Proposal";
            const probEl = document.getElementById("dealProbInput");
            if (probEl) probEl.value = "50";
            const ownerEl = document.getElementById("dealOwnerSelect");
            if (ownerEl) ownerEl.value = contact.owner || "Sarah Chen";
            const sourceEl = document.getElementById("dealSourceSelect");
            if (sourceEl) sourceEl.value = "Website";
            const descEl = document.getElementById("dealDescInput");
            if (descEl) descEl.value = "";

            openModal("createDealModal");
        }
    };

    // ─── Save: Log Activity ──────────────────────────────────────────────────────
    function saveLogActivitySubmit() {
        if (!activeTargetContact) return;

        const title = document.getElementById("activityTitleInput").value.trim();
        if (!title) { alert("Please enter an activity title."); return; }

        const type   = document.getElementById("activityTypeSelect").value;
        const outcome = document.getElementById("activityOutcomeSelect").value;
        const date   = document.getElementById("activityDateInput").value;
        const time   = document.getElementById("activityTimeInput").value;
        const desc   = document.getElementById("activityDescInput").value.trim();

        if (!date) { alert("Please select a date."); return; }

        // Determine display group
        const now = new Date();
        const selected = new Date(date);
        const todayStr  = now.toDateString();
        const selStr    = selected.toDateString();
        let group = "Earlier";
        if (selStr === todayStr) {
            group = "Today";
        } else {
            const diff = Math.round((now - selected) / 86400000);
            if (diff === 1) group = "Yesterday";
            else if (diff >= 2 && diff <= 6) group = "Earlier this week";
        }

        // Icon + colour per type
        const typeMap = {
            "Call":     { icon: "📞", color: "#12B76A" },
            "Email":    { icon: "✉️", color: "#2563EB" },
            "WhatsApp": { icon: "💬", color: "#25D366" },
            "Meeting":  { icon: "📅", color: "#7C3AED" },
            "Note":     { icon: "📌", color: "#7C3AED" },
            "Other":    { icon: "⭐", color: "#F79009" }
        };
        const meta = typeMap[type] || { icon: "⭐", color: "#F79009" };

        const timeDisplay = time
            ? `${date} ${time}`
            : `${date}`;

        const newActivity = {
            id:     `ACT-${Date.now()}`,
            group:  group,
            title:  title,
            desc:   desc || `${type} logged for ${activeTargetContact.name}.`,
            author: activeTargetContact.owner || "Sarah Chen",
            time:   time ? `${time}` : "Today",
            icon:   meta.icon,
            color:  meta.color,
            status: outcome,
            type:   type === "Call" || type === "WhatsApp" ? "Calls"
                  : type === "Email" ? "Emails"
                  : type === "Meeting" ? "Meetings"
                  : type === "Note" ? "Notes" : "Other"
        };

        const cid = activeTargetContact.id;
        if (!contactActivitiesStore[cid]) contactActivitiesStore[cid] = [];
        // Prepend so newest appears first
        contactActivitiesStore[cid].unshift(newActivity);
        localStorage.setItem(ACTIVITIES_KEY, JSON.stringify(contactActivitiesStore));

        // Refresh activity tab if it's currently visible
        if (activeContactForDetails && activeContactForDetails.id === cid && activeTabName === "activity") {
            renderTabPanelContent("activity", document.getElementById("cdTabContent"), activeContactForDetails);
        }

        closeModal("logActivityModal");
        showToast(`Activity logged for ${activeTargetContact.name}.`);
    }

    // ─── Save: Create Deal ───────────────────────────────────────────────────────
    function saveCreateDealSubmit() {
        if (!activeTargetContact) return;

        const name      = document.getElementById("dealNameInput").value.trim();
        const valueRaw  = document.getElementById("dealValueInput").value.trim();
        const stage     = document.getElementById("dealStageSelect").value;
        const closeDate = document.getElementById("dealCloseDateInput").value;

        if (!name)      { alert("Please enter a deal name."); return; }
        if (!valueRaw)  { alert("Please enter the deal value."); return; }
        if (!closeDate) { alert("Please select an expected close date."); return; }

        const value = parseFloat(valueRaw) || 0;
        const prob  = parseInt(document.getElementById("dealProbInput").value, 10) || 50;
        const owner = document.getElementById("dealOwnerSelect").value;
        const source= document.getElementById("dealSourceSelect").value;
        const desc  = document.getElementById("dealDescInput").value.trim();

        // Stage colour
        const stageColorMap = {
            "Prospect":    "#6B7280",
            "Qualified":   "#2563EB",
            "Proposal":    "#7C3AED",
            "Negotiation": "#F79009",
            "Closed Won":  "#12B76A",
            "Closed Lost": "#F04438"
        };
        const stageColor = stageColorMap[stage] || "#7C3AED";

        // Format close date for display
        const closeDateDisplay = new Date(closeDate + "T00:00:00").toLocaleDateString("en-US", {
            month: "short", day: "numeric", year: "numeric"
        });

        const newDeal = {
            id:         `DEAL-${Date.now()}`,
            name:       name,
            stage:      stage,
            stageColor: stageColor,
            value:      value,
            prob:       prob,
            close:      closeDateDisplay,
            owner:      owner,
            source:     source,
            desc:       desc
        };

        const cid = activeTargetContact.id;
        if (!contactDealsStore[cid]) contactDealsStore[cid] = [];
        contactDealsStore[cid].unshift(newDeal);
        localStorage.setItem(DEALS_KEY, JSON.stringify(contactDealsStore));

        // Update in-memory contact deal totals
        const contact = allContacts.find(c => c.id === cid);
        if (contact) {
            contact.openDealsCount = (contact.openDealsCount || 0) + 1;
            contact.openDealsValue = (contact.openDealsValue || 0) + value;
        }

        // Refresh deals tab if currently visible
        if (activeContactForDetails && activeContactForDetails.id === cid && activeTabName === "deals") {
            renderTabPanelContent("deals", document.getElementById("cdTabContent"), activeContactForDetails);
        }

        closeModal("createDealModal");
        showToast(`Deal created for ${activeTargetContact.name}.`);
    }


    function renderTabPanelContent(tabName, container, c) {
        if (tabName === "overview") {
            container.innerHTML = `
                <div class="contact-view-info-card">
                    <div class="contact-view-info-title">Contact Information</div>
                    <div class="contact-view-info-row">
                        <span class="contacts-info-label">Email</span>
                        <div>
                            <span class="contacts-info-val">${escapeHtml(c.email)}</span>
                            <button type="button" class="contact-view-copy-btn" title="Copy Email" onclick="window.contactsApp.copyToClipboard('${c.email}')">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="contact-view-info-row">
                        <span class="contacts-info-label">Phone</span>
                        <div>
                            <span class="contacts-info-val">${escapeHtml(c.phone)}</span>
                            <button type="button" class="contact-view-copy-btn" title="Copy Phone" onclick="window.contactsApp.copyToClipboard('${c.phone}')">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">WhatsApp</span> <span class="contacts-info-val">${escapeHtml(c.phone)}</span></div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">Location</span> <span class="contacts-info-val">${escapeHtml(c.location || 'San Francisco, CA')}</span></div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">LinkedIn</span> <span class="contacts-info-val" style="color: var(--primary);">${escapeHtml(c.linkedin || 'linkedin.com/in/contact')}</span></div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">Preferred Channel</span> <span class="contacts-info-val">Email</span></div>
                </div>

                <div class="contact-view-info-card">
                    <div class="contact-view-info-title">CRM Details</div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">Contact Owner</span> <span class="contacts-info-val">${escapeHtml(c.owner)}</span></div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">Source</span> <span class="contacts-info-val">${escapeHtml(c.source || 'Website')}</span></div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">Relationship</span> <span class="contacts-info-val">${escapeHtml(c.relationship)}</span></div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">Created Date</span> <span class="contacts-info-val">${escapeHtml(c.createdAt || 'Jan 2025')}</span></div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">Last Interaction</span> <span class="contacts-info-val">${escapeHtml(c.lastInteraction || 'Recently')} (${escapeHtml(c.lastInteractionType || 'Call')})</span></div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">Next Follow-up</span> <span class="contacts-info-val">Tomorrow, 10:00 AM</span></div>
                </div>

                <div class="contact-view-info-card">
                    <div class="contact-view-info-title">Company Information</div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">Company</span> <span class="contacts-info-val">${escapeHtml(c.company)}</span></div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">Industry</span> <span class="contacts-info-val">${escapeHtml(c.industry || 'Software')}</span></div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">Website</span> <span class="contacts-info-val" style="color: var(--primary);">www.${c.company.toLowerCase().replace(/[^a-z]/g, '')}.com</span></div>
                    <div class="contact-view-info-row"><span class="contacts-info-label">Company Size</span> <span class="contacts-info-val">50–200 employees</span></div>
                </div>
            `;
        } else if (tabName === "activity") {
            const defaultActivities = [
                { id: "a1", group: "Today", title: "Call completed", desc: `Spoke with ${c.name} about the upcoming proposal.`, author: c.owner, time: "2h ago", icon: "📞", color: "#12B76A", status: "Connected", type: "Calls" },
                { id: "a2", group: "Today", title: "WhatsApp conversation opened", desc: "Follow-up template sent regarding pricing terms.", author: c.owner, time: "4h ago", icon: "💬", color: "#25D366", status: "Sent", type: "Calls" },
                { id: "a3", group: "Yesterday", title: "Email application opened", desc: "Follow-up email prepared by Sarah Chen.", author: "Sarah Chen", time: "Yesterday", icon: "✉️", color: "#2563EB", status: "Drafted", type: "Emails" },
                { id: "a4", group: "Yesterday", title: "Meeting scheduled", desc: "Product demonstration on Friday at 11:00 AM.", author: "James Wu", time: "Yesterday", icon: "📅", color: "#7C3AED", status: "Confirmed", type: "Meetings" },
                { id: "a5", group: "Earlier this week", title: "Deal moved", desc: `${c.company} Enterprise Expansion moved to Proposal.`, author: c.owner, time: "3d ago", icon: "🚀", color: "#F79009", status: "Proposal", type: "Deals" },
                { id: "a6", group: "Earlier this week", title: "Note added", desc: `${c.name} prefers communication by email in the afternoon.`, author: "Sarah Chen", time: "4d ago", icon: "📌", color: "#7C3AED", status: "Note", type: "Notes" }
            ];
            const userActivities = contactActivitiesStore[c.id] || [];
            const activities = [...userActivities, ...defaultActivities];

            container.innerHTML = `
                <div class="contact-view-activity-header">
                    <select class="input-control input-sm" style="width: 140px; background-color: #FFFFFF;" onchange="window.contactsApp.filterActivityFeed(this.value)">
                        <option value="All">All Activity</option>
                        <option value="Calls">Calls & Messages</option>
                        <option value="Emails">Emails</option>
                        <option value="Meetings">Meetings</option>
                        <option value="Deals">Deals</option>
                        <option value="Notes">Notes</option>
                    </select>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-secondary btn-xs" onclick="showContactsToast('Filter activity log...')">Filter</button>
                        <button type="button" class="btn btn-primary btn-xs" onclick="window.contactsApp.openLogActivityModal()">+ Log Activity</button>
                    </div>
                </div>

                <div id="activityTimelineList">
                    ${renderTimelineGroups(activities)}
                </div>
            `;
        } else if (tabName === "deals") {
            const defaultDeals = [
                { id: "d1", name: `${c.company} Enterprise Expansion`, stage: "Proposal", stageColor: "#7C3AED", value: 24000, prob: 65, close: "Sep 18, 2026", owner: c.owner },
                { id: "d2", name: "Annual Support Package", stage: "Qualified", stageColor: "#2563EB", value: 10500, prob: 40, close: "Oct 04, 2026", owner: c.owner }
            ];
            const userDeals = contactDealsStore[c.id] || [];
            const dealsList = [...userDeals, ...defaultDeals];

            const closedStages = ["Closed Won", "Closed Lost"];
            const openDeals = dealsList.filter(d => !closedStages.includes(d.stage));
            const openCount = openDeals.length;
            const openVal   = openDeals.reduce((sum, d) => sum + (d.value || 0), 0);
            const wonVal    = dealsList.filter(d => d.stage === "Closed Won").reduce((sum, d) => sum + (d.value || 0), 0) + 78000;

            container.innerHTML = `
                <div class="contact-view-deals-summary">
                    <div style="flex: 1;">
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">OPEN DEALS</div>
                        <div style="font-size: 16px; font-weight: 700; color: var(--text-heading); margin-top: 2px;">${openCount}</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">PIPELINE VALUE</div>
                        <div style="font-size: 16px; font-weight: 700; color: var(--primary); margin-top: 2px;">$${openVal.toLocaleString()}</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 500;">WON VALUE</div>
                        <div style="font-size: 16px; font-weight: 700; color: #12B76A; margin-top: 2px;">$${wonVal.toLocaleString()}</div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span style="font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Associated Deals</span>
                    <button type="button" class="btn btn-primary btn-xs" onclick="window.contactsApp.openCreateDealModal()">+ Create Deal</button>
                </div>

                <div>
                    ${dealsList.map(d => `
                        <div class="contact-view-deal-card">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;">
                                <div>
                                    <div style="font-size: 13.5px; font-weight: 600; color: var(--text-heading);">${escapeHtml(d.name)}</div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">${escapeHtml(c.company)}</div>
                                </div>
                                <span class="contacts-badge" style="background-color: ${d.stageColor}18; color: ${d.stageColor};">${escapeHtml(d.stage)}</span>
                            </div>
                            <div style="margin-top: 10px; display: flex; align-items: center; justify-content: space-between; font-size: 12px;">
                                <strong style="font-size: 14px; color: var(--text-heading);">$${d.value.toLocaleString()}</strong>
                                <span style="font-size: 11px; color: var(--text-muted);">${d.prob}% probability</span>
                            </div>
                            <div class="contact-view-deal-progress">
                                <div class="contact-view-deal-bar" style="width: ${d.prob}%; background-color: ${d.stageColor};"></div>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px; color: var(--text-muted);">
                                <span>Close: ${escapeHtml(d.close)}</span>
                                <button type="button" class="btn btn-ghost btn-xs" style="color: var(--primary);" onclick="showContactsToast('Navigating to deal details...')">View Deal &rarr;</button>
                            </div>
                        </div>
                    `).join("")}
                </div>
            `;
        } else if (tabName === "tasks") {
            const tasksList = contactTasksStore[c.id] || [
                { id: "t1", title: "Follow-up call regarding Q3 budget", due: "Today, 4:00 PM", done: false, priority: "High", section: "Today", owner: c.owner },
                { id: "t2", title: "Schedule product demonstration", due: "Aug 12, 11:00 AM", done: false, priority: "Medium", section: "Upcoming", owner: c.owner },
                { id: "t3", title: "Review pricing requirements", due: "Yesterday", done: false, priority: "High", section: "Overdue", owner: c.owner },
                { id: "t4", title: "Send meeting reminder email", due: "Jul 28", done: true, priority: "Low", section: "Completed", owner: c.owner }
            ];

            const overdue = tasksList.filter(t => t.section === "Overdue" && !t.done);
            const today = tasksList.filter(t => (t.section === "Today" || !t.section) && !t.done);
            const upcoming = tasksList.filter(t => t.section === "Upcoming" && !t.done);
            const completed = tasksList.filter(t => t.done);

            container.innerHTML = `
                <div class="contact-view-tasks-toolbar">
                    <select class="input-control input-sm" style="width: 130px; background-color: #FFFFFF;" onchange="window.contactsApp.filterTasksList(this.value)">
                        <option value="All">All Tasks</option>
                        <option value="High">High Priority</option>
                        <option value="Today">Due Today</option>
                        <option value="Overdue">Overdue</option>
                    </select>
                    <button type="button" class="btn btn-primary btn-xs" onclick="window.contactsApp.openAddTaskModal('${c.id}')">+ Add Task</button>
                </div>

                <div id="tasksListContainer">
                    ${renderTaskSection("Overdue Tasks", overdue, "#B42318", c.id)}
                    ${renderTaskSection("Today", today, "#2563EB", c.id)}
                    ${renderTaskSection("Upcoming", upcoming, "#7C3AED", c.id)}
                    ${renderTaskSection("Completed Tasks", completed, "#027A48", c.id)}
                </div>
            `;
        } else if (tabName === "notes") {
            const notesList = contactNotesStore[c.id] || [
                { id: "n1", author: c.owner, text: `${c.name} is interested in the Enterprise plan and needs approval from the finance team.`, time: "Yesterday", pinned: true },
                { id: "n2", author: "Sarah Chen", text: "Prefers follow-ups by email in the afternoon.", time: "2d ago", pinned: false },
                { id: "n3", author: "James Wu", text: "Requested a revised pricing proposal before Friday.", time: "4d ago", pinned: false }
            ];

            container.innerHTML = `
                <div class="contact-view-note-composer">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                        <div class="avatar avatar-xs" style="background-color: #7C3AED;">SC</div>
                        <span style="font-size: 12px; font-weight: 600; color: var(--text-heading);">Add Note</span>
                        <label style="margin-left: auto; font-size: 11px; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 4px;">
                            <input type="checkbox" id="notePinCheck" style="accent-color: var(--primary);">
                            <span>📌 Pin Note</span>
                        </label>
                    </div>
                    <textarea class="input-control" id="newNoteTextarea" style="height: 64px; font-size: 12px; padding: 8px; resize: none;" placeholder="Write a note about this contact..."></textarea>
                    <div style="display: flex; justify-content: flex-end; gap: 6px; margin-top: 8px;">
                        <button type="button" class="btn btn-secondary btn-xs" onclick="document.getElementById('newNoteTextarea').value=''">Cancel</button>
                        <button type="button" class="btn btn-primary btn-xs" onclick="window.contactsApp.saveContactNote('${c.id}')">Save Note</button>
                    </div>
                </div>

                <div id="notesCardContainer">
                    ${notesList.length === 0 ? `
                        <div class="contact-view-empty-state">
                            <div class="contact-view-empty-title">No Notes Yet</div>
                            <p class="contact-view-empty-desc">Add a note above to record important insights.</p>
                        </div>
                    ` : notesList.map(n => `
                        <div class="contact-view-note-card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="font-weight: 600; color: var(--text-heading);">${escapeHtml(n.author)}</span>
                                    ${n.pinned ? '<span class="contact-view-pinned-badge">📌 Pinned</span>' : ''}
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 10px; color: var(--text-muted);">${escapeHtml(n.time)}</span>
                                    <button type="button" class="btn btn-ghost btn-xs" style="padding: 2px; color: #F04438;" onclick="window.contactsApp.deleteContactNote('${c.id}', '${n.id}')">Delete</button>
                                </div>
                            </div>
                            <p style="margin: 0; color: var(--text-body); line-height: 1.4;">${escapeHtml(n.text)}</p>
                        </div>
                    `).join("")}
                </div>
            `;
        }
    }

    function renderTimelineGroups(activities) {
        if (!activities || activities.length === 0) {
            return `
                <div class="contact-view-empty-state">
                    <div class="contact-view-empty-title">No Activity Recorded</div>
                    <p class="contact-view-empty-desc">No calls, emails or meetings logged for this contact.</p>
                </div>
            `;
        }

        const groups = {};
        activities.forEach(a => {
            const g = a.group || "Earlier";
            if (!groups[g]) groups[g] = [];
            groups[g].push(a);
        });

        let html = "";
        for (const [groupName, items] of Object.entries(groups)) {
            html += `
                <div class="contact-view-timeline-group">
                    <div class="contact-view-group-label">${escapeHtml(groupName)}</div>
                    <div class="contact-view-timeline">
                        ${items.map(item => `
                            <div class="contact-view-timeline-item">
                                <div class="contact-view-timeline-icon" style="background-color: ${item.color}18; color: ${item.color};">${item.icon}</div>
                                <div class="contact-view-timeline-card">
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                        <div style="font-weight: 600; color: var(--text-heading); font-size: 12.5px;">${escapeHtml(item.title)}</div>
                                        <span style="font-size: 10px; color: var(--text-muted);">${escapeHtml(item.time)}</span>
                                    </div>
                                    <p style="margin: 2px 0 4px; color: var(--text-secondary); font-size: 11.5px; line-height: 1.4;">${escapeHtml(item.desc)}</p>
                                    <div style="display: flex; align-items: center; justify-content: space-between; font-size: 10.5px; color: var(--text-muted);">
                                        <span>Logged by: <strong>${escapeHtml(item.author || 'Sarah Chen')}</strong></span>
                                        ${item.status ? `<span class="contacts-badge" style="background-color: ${item.color}18; color: ${item.color}; font-size: 9.5px; padding: 1px 6px;">${escapeHtml(item.status)}</span>` : ''}
                                    </div>
                                </div>
                            </div>
                        `).join("")}
                    </div>
                </div>
            `;
        }
        return html;
    }

    function renderTaskSection(title, tasks, color, contactId) {
        if (!tasks || tasks.length === 0) return "";
        return `
            <div class="contact-view-task-section">
                <div class="contact-view-task-section-title" style="color: ${color};">${escapeHtml(title)} (${tasks.length})</div>
                ${tasks.map(t => `
                    <div class="contact-view-task-item">
                        <div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0;">
                            <input type="checkbox" ${t.done ? 'checked' : ''} style="accent-color: var(--primary); width: 16px; height: 16px; cursor: pointer;" onchange="window.contactsApp.toggleTaskDone('${contactId}', '${t.id}', this.checked)">
                            <div style="min-width: 0;">
                                <div style="${t.done ? 'text-decoration: line-through; color: var(--text-muted);' : 'color: var(--text-heading); font-weight: 500;'}">${escapeHtml(t.title)}</div>
                                <div style="font-size: 10.5px; color: var(--text-muted);">Due: ${escapeHtml(t.due)} • Owner: ${escapeHtml(t.owner || 'Sarah Chen')}</div>
                            </div>
                        </div>
                        <span class="contact-view-priority ${t.priority.toLowerCase()}">${escapeHtml(t.priority)}</span>
                    </div>
                `).join("")}
            </div>
        `;
    }

    window.contactsApp.filterActivityFeed = function(filterType) {
        if (!activeContactForDetails) return;
        const container = document.getElementById("activityTimelineList");
        if (!container) return;

        const activities = contactActivitiesStore[activeContactForDetails.id] || [
            { id: "a1", group: "Today", title: "Call completed", desc: `Spoke with ${activeContactForDetails.name} about the upcoming proposal.`, author: activeContactForDetails.owner, time: "2h ago", icon: "📞", color: "#12B76A", status: "Connected", type: "Calls" },
            { id: "a2", group: "Today", title: "WhatsApp conversation opened", desc: "Follow-up template sent regarding pricing terms.", author: activeContactForDetails.owner, time: "4h ago", icon: "💬", color: "#25D366", status: "Sent", type: "Calls" },
            { id: "a3", group: "Yesterday", title: "Email application opened", desc: "Follow-up email prepared by Sarah Chen.", author: "Sarah Chen", time: "Yesterday", icon: "✉️", color: "#2563EB", status: "Drafted", type: "Emails" },
            { id: "a4", group: "Yesterday", title: "Meeting scheduled", desc: "Product demonstration on Friday at 11:00 AM.", author: "James Wu", time: "Yesterday", icon: "📅", color: "#7C3AED", status: "Confirmed", type: "Meetings" },
            { id: "a5", group: "Earlier this week", title: "Deal moved", desc: `${activeContactForDetails.company} Enterprise Expansion moved to Proposal.`, author: activeContactForDetails.owner, time: "3d ago", icon: "🚀", color: "#F79009", status: "Proposal", type: "Deals" },
            { id: "a6", group: "Earlier this week", title: "Note added", desc: `${activeContactForDetails.name} prefers communication by email in the afternoon.`, author: "Sarah Chen", time: "4d ago", icon: "📌", color: "#7C3AED", status: "Note", type: "Notes" }
        ];

        const filtered = filterType === "All" ? activities : activities.filter(a => a.type === filterType);
        container.innerHTML = renderTimelineGroups(filtered);
    };

    window.contactsApp.filterTasksList = function(filterVal) {
        if (!activeContactForDetails) return;
        const container = document.getElementById("tasksListContainer");
        if (!container) return;

        const tasksList = contactTasksStore[activeContactForDetails.id] || [
            { id: "t1", title: "Follow-up call regarding Q3 budget", due: "Today, 4:00 PM", done: false, priority: "High", section: "Today", owner: activeContactForDetails.owner },
            { id: "t2", title: "Schedule product demonstration", due: "Aug 12, 11:00 AM", done: false, priority: "Medium", section: "Upcoming", owner: activeContactForDetails.owner },
            { id: "t3", title: "Review pricing requirements", due: "Yesterday", done: false, priority: "High", section: "Overdue", owner: activeContactForDetails.owner },
            { id: "t4", title: "Send meeting reminder email", due: "Jul 28", done: true, priority: "Low", section: "Completed", owner: activeContactForDetails.owner }
        ];

        let filtered = tasksList;
        if (filterVal === "High") filtered = tasksList.filter(t => t.priority === "High");
        if (filterVal === "Today") filtered = tasksList.filter(t => t.section === "Today");
        if (filterVal === "Overdue") filtered = tasksList.filter(t => t.section === "Overdue");

        const overdue = filtered.filter(t => t.section === "Overdue" && !t.done);
        const today = filtered.filter(t => (t.section === "Today" || !t.section) && !t.done);
        const upcoming = filtered.filter(t => t.section === "Upcoming" && !t.done);
        const completed = filtered.filter(t => t.done);

        container.innerHTML = `
            ${renderTaskSection("Overdue Tasks", overdue, "#B42318", activeContactForDetails.id)}
            ${renderTaskSection("Today", today, "#2563EB", activeContactForDetails.id)}
            ${renderTaskSection("Upcoming", upcoming, "#7C3AED", activeContactForDetails.id)}
            ${renderTaskSection("Completed Tasks", completed, "#027A48", activeContactForDetails.id)}
        `;
    };

    window.contactsApp.toggleTaskDone = function(contactId, taskId, isDone) {
        if (!contactTasksStore[contactId]) return;
        const task = contactTasksStore[contactId].find(t => t.id === taskId);
        if (task) {
            task.done = isDone;
            localStorage.setItem(TASKS_KEY, JSON.stringify(contactTasksStore));
            window.contactsApp.switchDetailsTab("tasks");
        }
    };

    window.contactsApp.saveContactNote = function(contactId) {
        const textarea = document.getElementById("newNoteTextarea");
        const pinCheck = document.getElementById("notePinCheck");
        if (!textarea) return;
        const text = textarea.value.trim();
        if (!text) return;

        const isPinned = pinCheck ? pinCheck.checked : false;

        if (!contactNotesStore[contactId]) contactNotesStore[contactId] = [];
        const newNote = {
            id: String(Date.now()),
            author: activeContactForDetails ? activeContactForDetails.owner : "Sarah Chen",
            text: text,
            time: "Just now",
            pinned: isPinned
        };

        if (isPinned) {
            contactNotesStore[contactId].unshift(newNote);
        } else {
            contactNotesStore[contactId].push(newNote);
        }

        localStorage.setItem(NOTES_KEY, JSON.stringify(contactNotesStore));
        window.contactsApp.switchDetailsTab("notes");
        showContactsToast("Note saved.");
    };

    window.contactsApp.deleteContactNote = function(contactId, noteId) {
        if (confirm("Delete this note?")) {
            if (contactNotesStore[contactId]) {
                contactNotesStore[contactId] = contactNotesStore[contactId].filter(n => n.id !== noteId);
                localStorage.setItem(NOTES_KEY, JSON.stringify(contactNotesStore));
                window.contactsApp.switchDetailsTab("notes");
                showContactsToast("Note deleted.");
            }
        }
    };

    // saveTaskFormSubmit is defined earlier at line 404 — this duplicate is removed

    function saveContactFormSubmit() {
        const editId = document.getElementById("editContactId") ? document.getElementById("editContactId").value : "";
        const firstName = document.getElementById("addFirstName") ? document.getElementById("addFirstName").value.trim() : "";
        const lastName = document.getElementById("addLastName") ? document.getElementById("addLastName").value.trim() : "";
        const company = document.getElementById("addCompany") ? document.getElementById("addCompany").value.trim() : "";
        const email = document.getElementById("addEmail") ? document.getElementById("addEmail").value.trim() : "";
        const phone = document.getElementById("addPhone") ? document.getElementById("addPhone").value.trim() : "";
        const location = document.getElementById("addLocation") ? document.getElementById("addLocation").value.trim() : "San Francisco, CA";
        const rel = document.getElementById("addRelationship") ? document.getElementById("addRelationship").value : "Prospect";
        const owner = document.getElementById("addOwner") ? document.getElementById("addOwner").value : "Sarah Chen";

        if (!firstName || !lastName || !company || !email) {
            alert("Please fill in all required fields (First Name, Last Name, Company, Email).");
            return;
        }

        const fullName = `${firstName} ${lastName}`;

        if (editId) {
            const contact = allContacts.find(c => c.id === editId);
            if (contact) {
                contact.name = fullName;
                contact.title = document.getElementById("addTitle") ? document.getElementById("addTitle").value.trim() || contact.title : contact.title;
                contact.company = company;
                contact.email = email;
                contact.phone = phone || contact.phone;
                contact.cleanPhone = phone.replace(/[^0-9]/g, '') || contact.cleanPhone;
                contact.location = location;
                contact.relationship = rel;
                contact.owner = owner;

                const custom = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
                const cIdx = custom.findIndex(item => item.id === editId);
                if (cIdx >= 0) custom[cIdx] = contact;
                else custom.unshift(contact);
                localStorage.setItem(STORAGE_KEY, JSON.stringify(custom));

                showContactsToast("Contact updated in the frontend demo.");

                if (activeContactForDetails && activeContactForDetails.id === editId) {
                    window.contactsApp.openDetails(editId);
                }
            }
        } else {
            const newContact = {
                id: `C-${Date.now().toString().slice(-3)}`,
                name: fullName,
                title: document.getElementById("addTitle") ? document.getElementById("addTitle").value.trim() || "Manager" : "Manager",
                company: company,
                industry: "Software",
                relationship: rel,
                email: email,
                phone: phone || "+1 (555) 000-1122",
                cleanPhone: phone.replace(/[^0-9]/g, '') || "15550001122",
                countryCode: "+1",
                location: location,
                linkedin: "linkedin.com/in/contact",
                owner: owner,
                ownerInitials: owner.split(" ").map(n => n[0]).join("").toUpperCase(),
                ownerColor: "#059669",
                openDealsCount: 0,
                openDealsValue: 0,
                lastInteraction: "Just now",
                lastInteractionType: "Created",
                source: "Manual Entry",
                createdAt: "Today"
            };

            allContacts.unshift(newContact);

            const existingCustom = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
            existingCustom.unshift(newContact);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(existingCustom));

            showContactsToast(`Contact '${fullName}' created successfully!`);
        }

        window.contactsApp.closeAddDrawer();
        document.getElementById("addContactForm").reset();
        applyFilterAndSort();
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    window.showContactsToast = function(msg) {
        const toast = document.createElement("div");
        toast.style.cssText = "position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background-color: #101828; color: #ffffff; padding: 10px 18px; border-radius: 999px; font-size: 13px; font-weight: 500; box-shadow: 0 10px 15px rgba(0,0,0,0.2); z-index: 200; pointer-events: none; transition: opacity 0.3s ease;";
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = "0";
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    };

    // toggleContactRowMenu portal implementation is defined below — duplicate removed

    window.triggerContactsCall = function(id) {
        window.contactsApp.triggerCall(id);
    };

    window.triggerContactsWhatsApp = function(id) {
        window.contactsApp.triggerWA(id);
    };

    window.triggerContactsEmail = function(id) {
        window.contactsApp.triggerEmail(id);
    };

    let currentTriggerBtn = null;
    let activeRowContactId = null; // stable ID of contact whose row menu is open

    window.closeContactsActionMenu = function() {
        const menu = document.getElementById("contactActionsMenu");
        if (menu) {
            menu.classList.remove("show");
            menu.style.display = "none";
        }
        if (currentTriggerBtn && typeof currentTriggerBtn.setAttribute === "function") {
            currentTriggerBtn.setAttribute("aria-expanded", "false");
            currentTriggerBtn.classList.remove("is-open");
        }
        currentTriggerBtn = null;
        activeRowContactId = null;
    };

    document.addEventListener("click", function(e) {
        const trigger = e.target.closest("[data-contact-menu-trigger]");
        const menu = document.getElementById("contactActionsMenu");
        
        if (trigger) {
            e.preventDefault();
            e.stopPropagation();
            
            const id = trigger.getAttribute("data-contact-id");
            if (!id) return;
            
            if (activeRowContactId === id && menu && menu.classList.contains("show")) {
                window.closeContactsActionMenu();
                return;
            }

            window.closeContactsActionMenu();

            activeRowContactId = id;
            currentTriggerBtn = trigger;
            trigger.setAttribute("aria-expanded", "true");
            trigger.classList.add("is-open");
            
            if (menu) {
                menu.setAttribute("data-active-contact-id", id);
                const contact = allContacts.find(c => c.id === id);
                if (contact) {
                    const isInactive = (contact.relationship || "").toLowerCase() === "inactive";
                    const toggleBtn = menu.querySelector("[data-contact-action='inactive'], [data-contact-action='reactivate'], [data-contact-action='toggle-inactive']");
                    if (toggleBtn) {
                        if (isInactive) {
                            toggleBtn.setAttribute("data-contact-action", "reactivate");
                            toggleBtn.innerHTML = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>\n        <span class="inactive-text">Reactivate Contact</span>`;
                        } else {
                            toggleBtn.setAttribute("data-contact-action", "toggle-inactive");
                            toggleBtn.innerHTML = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>\n        <span class="inactive-text">Mark Inactive</span>`;
                        }
                    }
                }

                menu.style.display = "block";
                menu.style.visibility = "hidden";
                menu.classList.add("show");

                const rect = trigger.getBoundingClientRect();
                const menuWidth = menu.offsetWidth || 220;
                const menuHeight = menu.offsetHeight || 310;
                const vWidth = window.innerWidth;
                const vHeight = window.innerHeight;
                const margin = 12;
                const gap = 4;

                let left = rect.right - menuWidth;
                if (left < margin) left = margin;
                if (left + menuWidth > vWidth - margin) left = vWidth - margin - menuWidth;

                const spaceBelow = vHeight - rect.bottom - margin;
                const spaceAbove = rect.top - margin;
                let top = rect.bottom + gap;

                if (spaceBelow < menuHeight && spaceAbove > spaceBelow) {
                    top = rect.top - menuHeight - gap;
                }

                menu.style.left = `${left}px`;
                menu.style.top = `${top}px`;
                menu.style.visibility = "visible";
            }
            return;
        }

        if (menu && menu.classList.contains("show")) {
            if (!e.target.closest("#contactActionsMenu")) {
                window.closeContactsActionMenu();
            }
        }
    });

    function handleContactMenuAction(e) {
        const actionItem = e.target.closest("[data-contact-action]");
        if (!actionItem) return;

        e.preventDefault();
        e.stopPropagation();

        const action = actionItem.getAttribute("data-contact-action");
        const contactId = activeRowContactId;
        const triggerBtn = currentTriggerBtn;

        window.closeContactsActionMenu();

        if (!contactId) return;

        const contact = allContacts.find(c => c.id === contactId);
        if (!contact) return;

        if (action === "view") {
            window.contactsApp.openDetails(contact.id, triggerBtn);
        } else if (action === "edit") {
            window.contactsApp.openEditModal(contact.id);
        } else if (action === "add-task") {
            window.contactsApp.openAddTaskModal(contact.id);
        } else if (action === "add-note") {
            window.contactsApp.openAddNoteModal(contact.id);
        }
    }
    document.addEventListener("click", handleContactMenuAction);

    window.addEventListener("scroll", function() {
        window.closeContactsActionMenu();
    }, { passive: true });

    document.addEventListener("scroll", function(e) {
        if (e.target.closest && (e.target.closest(".crm-table-wrapper") || e.target.closest(".contacts-table-scroll"))) {
            window.closeContactsActionMenu();
        }
    }, { capture: true, passive: true });

    window.addEventListener("resize", function() {
        window.closeContactsActionMenu();
    });

    document.addEventListener("keydown", function(e) {
        const menu = document.getElementById("contactActionsMenu");
        if (menu && menu.classList.contains("show")) {
            if (e.key === "Escape") {
                const btnToFocus = currentTriggerBtn;
                window.closeContactsActionMenu();
                if (btnToFocus && typeof btnToFocus.focus === "function") {
                    btnToFocus.focus();
                }
            } else if (e.key === "ArrowDown" || e.key === "ArrowUp") {
                const items = Array.from(menu.querySelectorAll(".contact-action-item"));
                if (items.length === 0) return;
                e.preventDefault();
                const index = items.indexOf(document.activeElement);
                if (e.key === "ArrowDown") {
                    const next = index < items.length - 1 ? index + 1 : 0;
                    items[next].focus();
                } else {
                    const prev = index > 0 ? index - 1 : items.length - 1;
                    items[prev].focus();
                }
            }
        }
    });

    // Handle Custom Checkbox Clicks in Contacts Table
    document.addEventListener("click", function(e) {
        const cb = e.target.closest(".custom-checkbox");
        if (!cb) return;

        if (cb.id === "contactsSelectAll") {
            const isChecked = cb.classList.contains("checked");
            cb.classList.toggle("checked", !isChecked);
            selectedContactIds.clear();

            const visibleRows = document.querySelectorAll("#contactsTableBody tr");
            visibleRows.forEach(tr => {
                const rowCb = tr.querySelector(".row-checkbox");
                if (rowCb) rowCb.classList.toggle("checked", !isChecked);
                tr.classList.toggle("selected", !isChecked);
                if (!isChecked) {
                    const id = tr.getAttribute("data-id");
                    if (id) selectedContactIds.add(id);
                }
            });
            updateBulkToolbarState();
        } else if (cb.classList.contains("row-checkbox")) {
            const id = cb.getAttribute("data-id");
            const tr = cb.closest("tr");
            const isChecked = cb.classList.contains("checked");

            cb.classList.toggle("checked", !isChecked);
            if (tr) tr.classList.toggle("selected", !isChecked);

            if (!isChecked) {
                if (id) selectedContactIds.add(id);
            } else {
                if (id) selectedContactIds.delete(id);
            }

            const selectAll = document.getElementById("contactsSelectAll");
            if (selectAll && isChecked) selectAll.classList.remove("checked");
            updateBulkToolbarState();
        }
    });
})();

