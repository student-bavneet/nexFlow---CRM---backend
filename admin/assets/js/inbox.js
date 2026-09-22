/**
 * NexFlow CRM Unified Inbox Controller JavaScript
 * Database-backed, multi-tenant inbox controller.
 * Handles conversation selection, live threads, MySQL-driven counts,
 * folder/channel/team filtering, search, sorting, star/snooze/archive/status persistence,
 * message thread rendering, contact context drawer, and compose flow.
 */

(function() {
    let allConversations = [];
    let activeConvId = null;
    let activeContactId = null; // ID of conversation whose contact context drawer is open
    let activeThreadConversation = null;

    let activeFolder = "all";
    let activeChannel = "All";
    let activeTeam = "All";
    let searchQuery = "";
    let sortBy = "newest";
    let searchDebounceTimer = null;
    let lastFocusedElement = null;

    // Apply two-panel (no selection) or four-panel (conversation open) CSS state.
    function applyInboxLayoutState() {
        const root = document.querySelector(".inbox-page");
        if (!root) return;
        if (activeConvId) {
            root.classList.add("inbox-page--conversation-open");
            root.classList.remove("inbox-page--no-selection");
        } else {
            root.classList.add("inbox-page--no-selection");
            root.classList.remove("inbox-page--conversation-open");
        }
    }

    function initInboxPage(forceReinit) {
        const root = document.querySelector(".inbox-page");
        if (!root) return; // Exit safely if not on Inbox page

        if (window._inboxPageInitialized && !forceReinit) return;
        window._inboxPageInitialized = true;

        activeConvId = null;

        setupEventListeners();
        fetchAndApplyInboxPreferences();
        loadCrmLookups();
        loadConversations();
        loadCounts();
    }

    function fetchAndApplyInboxPreferences() {
        fetch('/nexFlow/admin/api/settings.php?action=inbox_preferences', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success && (data.data || data.settings)) {
                const prefs = data.data || data.settings;

                // Apply default folder if saved
                if (prefs.inbox_default_folder && prefs.inbox_default_folder !== '') {
                    activeFolder = prefs.inbox_default_folder;
                    document.querySelectorAll(".inbox-nav .inbox-folder-item").forEach(function(item) {
                        const folderAttr = item.getAttribute("onclick") || '';
                        item.classList.remove("active");
                        if (folderAttr.indexOf("'" + activeFolder + "'") !== -1) {
                            item.classList.add("active");
                        }
                    });
                }

                // Apply sort order if saved
                if (prefs.inbox_sort_order && prefs.inbox_sort_order !== '') {
                    sortBy = prefs.inbox_sort_order;
                    const sortSelect = document.getElementById("inboxSortSelect");
                    if (sortSelect) sortSelect.value = sortBy;
                }

                // Store preferences on window
                window._inboxPrefs = {
                    markReadOnOpen: prefs.inbox_mark_read_on_open !== undefined ? !!prefs.inbox_mark_read_on_open : true,
                    showContactContext: prefs.inbox_show_contact_context !== undefined ? !!prefs.inbox_show_contact_context : true
                };
            }
        })
        .catch(function(err) {
            console.error("Failed to load inbox preferences:", err);
            window._inboxPrefs = { markReadOnOpen: true, showContactContext: true };
        });
    }

    window.initInboxPage = initInboxPage;

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function() { initInboxPage(); });
    } else {
        initInboxPage();
    }

    function setupEventListeners() {
        const root = document.querySelector(".inbox-page");
        if (!root) return;

        const searchInput = document.getElementById("inboxSearchInput");
        if (searchInput) {
            searchInput.addEventListener("input", function(e) {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => {
                    searchQuery = e.target.value.trim();
                    loadConversations();
                }, 250);
            });
        }

        const composeForm = document.getElementById("composeMessageForm");
        if (composeForm) {
            composeForm.addEventListener("submit", function(e) {
                e.preventDefault();
                submitComposeForm();
            });
        }

        // Delegated click handler for split conversation-row actions
        const convList = document.getElementById("inboxConvList");
        if (convList) {
            convList.addEventListener("click", function(e) {
                const draftTarget = e.target.closest('[data-inbox-action="open-draft"]');
                if (draftTarget) {
                    e.stopPropagation();
                    const draftId = draftTarget.dataset.draftId;
                    if (draftId) window.inboxApp.openDraftInCompose(draftId);
                    return;
                }
                const contactTarget = e.target.closest('[data-inbox-action="open-contact"]');
                if (contactTarget) {
                    e.stopPropagation();
                    const convId = contactTarget.dataset.convId;
                    if (convId) window.inboxApp.openContactContext(convId, contactTarget);
                    return;
                }
                const convTarget = e.target.closest('[data-inbox-action="open-conversation"]');
                if (convTarget) {
                    const convId = convTarget.dataset.convId;
                    if (convId) window.inboxApp.selectConversation(convId);
                }
            });
            convList.addEventListener("keydown", function(e) {
                if (e.key !== "Enter" && e.key !== " ") return;
                const draftTarget = e.target.closest('[data-inbox-action="open-draft"]');
                if (draftTarget) {
                    e.preventDefault();
                    e.stopPropagation();
                    const draftId = draftTarget.dataset.draftId;
                    if (draftId) window.inboxApp.openDraftInCompose(draftId);
                    return;
                }
                const contactTarget = e.target.closest('[data-inbox-action="open-contact"]');
                if (contactTarget) {
                    e.preventDefault();
                    e.stopPropagation();
                    const convId = contactTarget.dataset.convId;
                    if (convId) window.inboxApp.openContactContext(convId, contactTarget);
                    return;
                }
                const convTarget = e.target.closest('[data-inbox-action="open-conversation"]');
                if (convTarget) {
                    e.preventDefault();
                    const convId = convTarget.dataset.convId;
                    if (convId) window.inboxApp.selectConversation(convId);
                }
            });
        }

        // Contact, Company, and Deal drawer backdrops click to close
        const contactDrawer = document.getElementById("inboxContactDrawer");
        if (contactDrawer) {
            contactDrawer.addEventListener("click", function(e) {
                if (e.target === contactDrawer) window.inboxApp.closeContactDrawer();
            });
        }
        const companyDrawer = document.getElementById("inboxCompanyDrawer");
        if (companyDrawer) {
            companyDrawer.addEventListener("click", function(e) {
                if (e.target === companyDrawer) window.inboxApp.closeCompanyDrawer();
            });
        }
        const dealDrawer = document.getElementById("inboxDealDrawer");
        if (dealDrawer) {
            dealDrawer.addEventListener("click", function(e) {
                if (e.target === dealDrawer) window.inboxApp.closeDealDrawer();
            });
        }

        // Global Backdrops & ESC
        document.querySelectorAll("#inboxComposeDrawer, #inboxFilterDrawer, #inboxSnoozeModal, #inboxEditRelationshipModal").forEach(drawer => {
            drawer.addEventListener("click", function(e) {
                if (e.target === drawer) {
                    if (drawer.id === "inboxComposeDrawer") window.inboxApp.closeComposeDrawer();
                    if (drawer.id === "inboxFilterDrawer") window.inboxApp.closeFilterDrawer();
                    if (drawer.id === "inboxSnoozeModal") window.inboxApp.closeSnoozeModal();
                    if (drawer.id === "inboxEditRelationshipModal") window.inboxApp.closeEditRelationshipModal();
                }
            });
        });

        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                const contactDrawer = document.getElementById("inboxContactDrawer");
                if (contactDrawer && contactDrawer.classList.contains("show")) {
                    window.inboxApp.closeContactDrawer();
                    return;
                }
                const companyDrawer = document.getElementById("inboxCompanyDrawer");
                if (companyDrawer && companyDrawer.classList.contains("show")) {
                    window.inboxApp.closeCompanyDrawer();
                    return;
                }
                const dealDrawer = document.getElementById("inboxDealDrawer");
                if (dealDrawer && dealDrawer.classList.contains("show")) {
                    window.inboxApp.closeDealDrawer();
                    return;
                }
                window.inboxApp.closeComposeDrawer();
                window.inboxApp.closeFilterDrawer();
                window.inboxApp.closeSnoozeModal();
                window.inboxApp.closeEditRelationshipModal();
            }
        });
    }

    // Load Conversations from MySQL API
    function loadConversations(preserveActive = true) {
        const params = new URLSearchParams({
            action: 'list',
            folder: activeFolder,
            channel: activeChannel,
            team: activeTeam,
            sort: sortBy
        });
        if (searchQuery) {
            params.append('search', searchQuery);
        }

        fetch('/nexFlow/admin/api/inbox.php?' + params.toString(), {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data && Array.isArray(res.data.conversations)) {
                allConversations = res.data.conversations;
            } else {
                allConversations = [];
            }

            renderConvList();

            if (activeConvId) {
                const stillExists = allConversations.some(c => String(c.id) === String(activeConvId));
                if (!stillExists && !preserveActive) {
                    activeConvId = null;
                }
            }

            applyInboxLayoutState();
            if (!activeConvId) {
                renderActiveThread();
            }
        })
        .catch(err => {
            console.error("Failed to load conversations:", err);
            allConversations = [];
            renderConvList();
            renderActiveThread();
        });
    }

    // Load Dynamic Counts from MySQL API
    function loadCounts() {
        fetch('/nexFlow/admin/api/inbox.php?action=counts', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                const d = res.data;
                const setTxt = (id, val) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = val;
                };

                setTxt("inboxTotalUnreadBadge", `${d.unread || 0} unread`);
                setTxt("count_all", d.all || 0);
                setTxt("count_unread", d.unread || 0);
                setTxt("count_assigned", d.assigned || 0);
                setTxt("count_starred", d.starred || 0);
                setTxt("count_snoozed", d.snoozed || 0);
                setTxt("count_archived", d.archived || 0);
                setTxt("count_drafts", d.drafts || 0);
                setTxt("count_email", d.email || 0);
                setTxt("count_whatsapp", d.whatsapp || 0);
                setTxt("count_sales_team", d.sales_team || 0);
                setTxt("count_support_team", d.support_team || 0);

                // Update sidebar navigation badge if available
                if (typeof window.updateSidebarBadges === "function") {
                    window.updateSidebarBadges({ inbox: d.unread || 0 });
                } else {
                    const sbBadge = document.querySelector('.sidebar-nav-item[href="inbox.php"] .sidebar-badge');
                    if (sbBadge) {
                        const unreadNum = parseInt(d.unread, 10) || 0;
                        if (unreadNum > 0) {
                            sbBadge.textContent = unreadNum;
                            sbBadge.style.display = "";
                        } else {
                            sbBadge.style.display = "none";
                        }
                    }
                }
            }
        })
        .catch(err => console.error("Failed to load inbox counts:", err));
    }

    function renderConvList() {
        const container = document.getElementById("inboxConvList");
        if (!container) return;

        if (allConversations.length === 0) {
            container.innerHTML = `
                <div class="tasks-empty-state" style="padding: 30px 16px;">
                    <div style="font-weight: 600; font-size: 13.5px; color: var(--text-heading); margin-bottom: 4px;">No Conversations</div>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 0 0 10px;">No messages match your selected folder or search query.</p>
                    <button type="button" class="btn btn-secondary btn-xs" onclick="window.inboxApp.resetFilters()">Reset Filters</button>
                </div>
            `;
            return;
        }

        const hintHtml = !activeConvId
            ? `<div class="inbox-select-hint">Select a conversation to view messages.</div>`
            : '';

        container.innerHTML = hintHtml + allConversations.map(c => {
            if (c.is_draft) {
                return `
                    <div class="inbox-conv-item"
                         role="listitem"
                         data-draft-id="${c.draft_id}"
                         data-inbox-action="open-draft"
                         style="cursor: pointer;"
                         title="Click to resume editing draft">
                        <div class="inbox-contact-zone" style="pointer-events: none;">
                            <div class="avatar avatar-md" style="background-color: #64748B;" aria-hidden="true">📝</div>
                            <div class="inbox-contact-meta">
                                <span class="inbox-contact-name" style="font-weight: 600; font-size: 13px;">${escapeHtml(c.contact)}</span>
                                <span class="inbox-contact-company" style="font-size: 11px;">Draft <span style="color:var(--text-muted)">• ${c.channelIcon || '✉️'} ${escapeHtml(c.channel)}</span></span>
                            </div>
                        </div>
                        <div class="inbox-conv-zone" style="pointer-events: none;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px; margin-bottom: 2px;">
                                <span style="font-weight: 500; font-size: 12px; color: var(--text-heading); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(c.subject)}</span>
                                <span style="font-size: 10.5px; color: var(--text-muted); flex-shrink: 0;">${escapeHtml(c.timestamp)}</span>
                            </div>
                            <div style="font-size: 11.5px; color: var(--text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 6px;">${escapeHtml(c.preview)}</div>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span class="tasks-tab-chip" style="font-size: 10px; padding: 1px 6px; background-color: #F1F5F9; color: #475569;">Draft</span>
                                <span style="font-size: 10px; color: var(--primary); font-weight: 500;">Click to Edit &rarr;</span>
                            </div>
                        </div>
                    </div>
                `;
            }

            const isActive = String(c.id) === String(activeConvId);
            const prioritySlug = (c.priority || "Normal").toLowerCase();

            return `
                <div class="inbox-conv-item ${isActive ? 'active' : ''}"
                     role="listitem"
                     data-conv-id="${c.id}">
                    ${c.unread ? '<span class="inbox-unread-dot" title="Unread conversation"></span>' : ''}

                    <!-- Contact Zone: avatar + name + company — opens Contact Context -->
                    <div class="inbox-contact-zone"
                         data-inbox-action="open-contact"
                         data-conv-id="${c.id}"
                         role="button"
                         tabindex="0"
                         aria-label="View contact details for ${escapeHtml(c.contact)}"
                         title="View contact details for ${escapeHtml(c.contact)}">
                        <div class="avatar avatar-md" style="background-color: ${c.avatarBg || '#7C3AED'};" aria-hidden="true">${escapeHtml(c.avatar)}</div>
                        <div class="inbox-contact-meta">
                            <span class="inbox-contact-name" style="font-weight: ${c.unread ? '700' : '600'}; font-size: 13px;">${escapeHtml(c.contact)}</span>
                            <span class="inbox-contact-company" style="font-size: 11px;">${escapeHtml(c.company)} <span style="color:var(--text-muted)">• ${c.channelIcon || '✉️'} ${escapeHtml(c.channel)}</span></span>
                        </div>
                    </div>

                    <!-- Conversation Zone: subject + preview + meta — opens chat -->
                    <div class="inbox-conv-zone"
                         data-inbox-action="open-conversation"
                         data-conv-id="${c.id}"
                         role="button"
                         tabindex="0"
                         aria-label="Open conversation with ${escapeHtml(c.contact)}: ${escapeHtml(c.subject)}"
                         title="Open conversation">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px; margin-bottom: 2px;">
                            <span style="font-weight: ${c.unread ? '600' : '500'}; font-size: 12px; color: var(--text-heading); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(c.subject)}</span>
                            <span style="font-size: 10.5px; color: var(--text-muted); flex-shrink: 0;">${escapeHtml(c.timestamp)}</span>
                        </div>
                        <div style="font-size: 11.5px; color: var(--text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 6px;">${escapeHtml(c.preview)}</div>
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span class="tasks-priority ${prioritySlug}" style="font-size: 10px; padding: 1px 5px;">${escapeHtml(c.priority)}</span>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <button type="button" class="inbox-star-btn ${c.starred ? 'starred' : ''}" onclick="window.inboxApp.toggleStar('${c.id}', event)" title="${c.starred ? 'Starred' : 'Star conversation'}">★</button>
                                <div class="avatar avatar-xs" style="background-color: ${c.ownerColor || '#7C3AED'}; font-size: 9px;" title="Assigned to ${escapeHtml(c.owner)}">${escapeHtml(c.ownerInitials || 'SC')}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join("");
    }

    function renderActiveThread(threadData = null) {
        const headerEl = document.getElementById("inboxThreadHeader");
        const bodyEl = document.getElementById("inboxThreadBody");
        const compEl = document.getElementById("inboxReplyComposer");

        if (!activeConvId) {
            activeThreadConversation = null;
            if (headerEl) headerEl.innerHTML = '<div style="font-size: 13px; color: var(--text-muted);">Select a conversation from the list.</div>';
            if (bodyEl) bodyEl.innerHTML = '<div class="tasks-empty-state" style="margin: auto;">Select a conversation to view message thread.</div>';
            if (compEl) compEl.innerHTML = '';
            return;
        }

        const c = threadData || allConversations.find(item => String(item.id) === String(activeConvId));
        activeThreadConversation = c || null;

        if (!c) {
            if (headerEl) headerEl.innerHTML = '<div style="font-size: 13px; color: var(--text-muted);">Loading conversation...</div>';
            if (bodyEl) bodyEl.innerHTML = '<div class="tasks-empty-state" style="margin: auto;">Loading message thread...</div>';
            if (compEl) compEl.innerHTML = '';
            return;
        }

        // Snooze indicator & buttons
        let snoozeHtml = '';
        if (c.snoozed_until && new Date(c.snoozed_until.replace(' ', 'T')) > new Date()) {
            const sDate = new Date(c.snoozed_until.replace(' ', 'T'));
            const sFormatted = sDate.toLocaleDateString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            snoozeHtml = `
                <span class="inbox-thread-snooze-chip" title="Snoozed until ${escapeHtml(c.snoozed_until)}">⏰ Snoozed (${sFormatted})</span>
                <button type="button" class="btn btn-secondary btn-xs" onclick="window.inboxApp.unsnoozeConversation('${c.id}')" title="Restore conversation to active inbox">Unsnooze</button>
            `;
        } else {
            snoozeHtml = `
                <button type="button" class="btn btn-ghost btn-xs" onclick="window.inboxApp.openSnoozeModal('${c.id}')" title="Snooze conversation">⏰ Snooze</button>
            `;
        }

        // In-context CRM Drawer View actions
        const contactId = c.contact_id ? String(c.contact_id) : '';
        const hasContact = Boolean(c.contact_id);
        const hasCompanyRel = Boolean(c.company_id);
        const hasDealRel = Boolean(c.deal_id);

        const contactBtn = `<button type="button" class="btn btn-secondary btn-xs" id="inboxViewContactBtn" data-contact-id="${contactId}" onclick="window.inboxApp.openContactDrawer('${c.id}', this)" title="${hasContact ? 'View contact details' : 'Contact not linked'}" ${!hasContact ? 'disabled style="opacity:0.45;cursor:not-allowed;"' : ''}>View Contact</button>`;
        const companyBtn = `<button type="button" class="btn btn-secondary btn-xs" onclick="window.inboxApp.openCompanyDrawer('${c.id}', this)" title="${hasCompanyRel ? 'View company details' : 'Company not linked'}" ${!hasCompanyRel ? 'disabled style="opacity:0.45;cursor:not-allowed;"' : ''}>View Company</button>`;
        const dealBtn = `<button type="button" class="btn btn-secondary btn-xs" onclick="window.inboxApp.openDealDrawer('${c.id}', this)" title="${hasDealRel ? 'View deal details' : 'Deal not linked'}" ${!hasDealRel ? 'disabled style="opacity:0.45;cursor:not-allowed;"' : ''}>View Deal</button>`;
        const linksBtn = `<button type="button" class="btn btn-secondary btn-xs" onclick="window.inboxApp.openEditRelationshipModal('${c.id}')" title="Link or change Contact, Company, or Deal">🔗 Links</button>`;

        // Contact info lines
        const hasCompany = c.company && c.company !== 'N/A';
        const hasTitle = c.title && c.title !== 'Contact' && c.title !== '';
        let companyDisplay = '';
        let companyTitle = '';
        if (hasCompany) {
            companyDisplay = hasTitle ? `${escapeHtml(c.title)} • ${escapeHtml(c.company)}` : escapeHtml(c.company);
            companyTitle = hasTitle ? `${c.title} • ${c.company}` : c.company;
        } else if (hasTitle) {
            companyDisplay = escapeHtml(c.title);
            companyTitle = c.title;
        } else {
            companyDisplay = '<span class="inbox-empty-text">N/A</span>';
            companyTitle = 'No company linked';
        }

        const channelSub = c.email || c.phone || '';
        const channelText = channelSub ? `${c.channel} • ${channelSub}` : c.channel;
        const channelTitle = `${c.channel}${channelSub ? ': ' + channelSub : ''}`;

        // Header
        if (headerEl) {
            headerEl.innerHTML = `
                <div class="inbox-thread-header-left">
                    <button type="button"
                        id="inboxCloseThreadBtn"
                        class="inbox-close-thread-btn"
                        onclick="window.inboxApp.closeThread()"
                        aria-label="Back to conversation list"
                        title="Back to conversation list">
                        &#8592;
                    </button>
                    <!-- Clickable contact identity: avatar + name → opens Contact Context -->
                    <button type="button"
                        class="inbox-thread-contact-btn"
                        onclick="window.inboxApp.openContactDrawer('${c.id}', this)"
                        aria-label="View contact details for ${escapeHtml(c.contact)}"
                        title="View contact details for ${escapeHtml(c.contact)}">
                        <div class="avatar avatar-md inbox-thread-avatar" style="background-color: ${c.avatarBg || '#7C3AED'};" aria-hidden="true">${escapeHtml(c.avatar)}</div>
                        <div class="inbox-thread-contact-info" id="inboxThreadHeading" tabindex="-1">
                            <div class="inbox-thread-contact-name" title="${escapeHtml(c.contact)}">${escapeHtml(c.contact)}</div>
                            <div class="inbox-thread-company-name" title="${escapeHtml(companyTitle)}">${companyDisplay}</div>
                            <div class="inbox-thread-channel-info" title="${escapeHtml(channelTitle)}">${c.channelIcon || '✉️'} <span>${escapeHtml(channelText)}</span></div>
                        </div>
                    </button>
                </div>
                <div class="inbox-thread-header-actions">
                    <div class="inbox-thread-actions-group">
                        <select class="inbox-thread-status-select" title="Change conversation status" onchange="window.inboxApp.changeStatus('${c.id}', this.value)">
                            <option value="Open" ${c.status === 'Open' ? 'selected' : ''}>Open</option>
                            <option value="Pending" ${c.status === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Waiting for Customer" ${c.status === 'Waiting for Customer' ? 'selected' : ''}>Waiting</option>
                            <option value="Resolved" ${c.status === 'Resolved' ? 'selected' : ''}>Resolved</option>
                            <option value="Archived" ${c.status === 'Archived' ? 'selected' : ''}>Archived</option>
                        </select>
                        ${contactBtn}
                        ${companyBtn}
                        ${dealBtn}
                        ${linksBtn}
                    </div>
                    <div class="inbox-thread-actions-group">
                        ${snoozeHtml}
                        <button type="button" class="btn btn-ghost btn-xs" onclick="window.inboxApp.toggleStar('${c.id}', event)" title="${c.starred ? 'Unstar conversation' : 'Star conversation'}">${c.starred ? '★ Starred' : '☆ Star'}</button>
                        <button type="button" class="btn btn-ghost btn-xs" onclick="${c.status === 'Archived' ? `window.inboxApp.unarchiveConversation('${c.id}')` : `window.inboxApp.archiveConversation('${c.id}')`}" title="${c.status === 'Archived' ? 'Unarchive conversation' : 'Archive conversation'}">${c.status === 'Archived' ? 'Unarchive' : 'Archive'}</button>
                    </div>
                </div>
            `;

            const heading = document.getElementById("inboxThreadHeading");
            if (heading) heading.focus();
        }

        // Body
        if (bodyEl) {
            const msgs = c.messages || [];
            let bodyHtml = `
                <div style="text-align: center; margin-bottom: 8px;">
                    <span style="font-size: 10.5px; font-weight: 600; color: var(--text-muted); background-color: #E4E7EC; padding: 2px 10px; border-radius: 999px;">${escapeHtml(c.dateStr || 'Today')}</span>
                </div>
            `;

            if (msgs.length === 0) {
                bodyHtml += `<div class="tasks-empty-state" style="padding: 20px 0;">No messages recorded in this conversation yet.</div>`;
            } else {
                bodyHtml += msgs.map(m => {
                    if (c.channel === "WhatsApp") {
                        const isIncoming = m.senderType === "contact" || m.direction === "inbound";
                        return `
                            <div class="inbox-whatsapp-bubble ${isIncoming ? 'incoming' : 'outgoing'}">
                                <div style="font-weight: 600; font-size: 11px; margin-bottom: 2px;">${escapeHtml(m.sender)}</div>
                                <div>${escapeHtml(m.content)}</div>
                                <div style="font-size: 9.5px; color: var(--text-muted); text-align: right; margin-top: 4px;">${escapeHtml(m.time)}</div>
                            </div>
                        `;
                    } else if (m.senderType === "internal_note" || m.direction === "internal") {
                        return `
                            <div class="inbox-internal-note-card">
                                <div style="font-weight: 700; font-size: 11.5px; margin-bottom: 4px;">📌 Internal Note — ${escapeHtml(m.sender)} (${escapeHtml(m.time)})</div>
                                <div>${escapeHtml(m.content)}</div>
                                <div style="font-size: 10px; opacity: 0.8; margin-top: 4px;">Visible to internal team members only.</div>
                            </div>
                        `;
                    } else {
                        return `
                            <div class="inbox-email-card">
                                <div class="inbox-email-card-header">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div class="avatar avatar-sm" style="background-color: ${m.senderType === 'contact' ? (c.avatarBg || '#7C3AED') : '#059669'};">${m.senderType === 'contact' ? escapeHtml(c.avatar) : 'SC'}</div>
                                        <div>
                                            <div style="font-weight: 600; font-size: 13px; color: var(--text-heading);">${escapeHtml(m.sender)}</div>
                                            <div style="font-size: 11px; color: var(--text-muted);">Subject: ${escapeHtml(c.subject)}</div>
                                        </div>
                                    </div>
                                    <span style="font-size: 11px; color: var(--text-muted);">${escapeHtml(m.time)}</span>
                                </div>
                                <div style="font-size: 13px; color: var(--text-body); line-height: 1.5; white-space: pre-wrap; overflow-wrap: anywhere;">${escapeHtml(m.content)}</div>
                            </div>
                        `;
                    }
                }).join("");
            }

            bodyEl.innerHTML = bodyHtml;
            bodyEl.scrollTop = bodyEl.scrollHeight;
        }

        // Composer
        if (compEl) {
            compEl.innerHTML = `
                <div class="inbox-composer-tabs">
                    <button type="button" class="inbox-composer-tab active" id="tabCompReply" onclick="window.inboxApp.switchComposerTab('reply')">Reply via ${escapeHtml(c.channel)}</button>
                    <button type="button" class="inbox-composer-tab" id="tabCompNote" onclick="window.inboxApp.switchComposerTab('note')">Add Internal Note</button>
                </div>
                <div id="composerBodyArea">
                    <textarea class="input-control" id="replyTextarea" style="height: 60px; font-size: 12.5px; resize: none; margin-bottom: 8px;" placeholder="Write your reply to ${escapeHtml(c.contact)}..."></textarea>
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 10.5px; color: var(--text-muted);">Pressing send will record the message in CRM history${c.channel === 'WhatsApp' ? ' and open wa.me' : ' and open your email client'}.</span>
                        <button type="button" class="btn btn-primary btn-xs" onclick="window.inboxApp.sendReply('${c.id}')">${c.channel === 'WhatsApp' ? 'Continue to WhatsApp' : 'Send & Open Email'}</button>
                    </div>
                </div>
            `;
        }
    }

    // Contact, Company, and Deal Drawer HTML Builders
    function buildEmptyContactHtml(convId) {
        return `
            <div style="padding: 36px 16px; text-align: center;">
                <div style="font-size: 38px; margin-bottom: 12px;">👤</div>
                <div style="font-size: 15px; font-weight: 700; color: var(--text-heading); margin-bottom: 6px;">Contact Not Linked</div>
                <div style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.5;">
                    This conversation is not currently linked to an existing CRM contact.
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.inboxApp.closeContactDrawer(); window.inboxApp.openEditRelationshipModal('${convId}');">
                    🔗 Link Contact
                </button>
            </div>
        `;
    }

    function buildContactContextHtml(c) {
        const channelLabel = c.channel === 'WhatsApp' ? '💬 WhatsApp' : '✉️ Email';
        const tags = (c.tags && Array.isArray(c.tags)) ? c.tags : ['Contact', 'Client'];
        const assocCompaniesHtml = (c.assoc_companies && c.assoc_companies.length > 0)
            ? `
                <div class="inbox-cctx-section">
                    <div class="inbox-cctx-section-title">Associated Companies</div>
                    ${c.assoc_companies.map(cp => `
                        <div class="inbox-cctx-row">
                            <span class="inbox-cctx-label" style="font-weight:600;">🏢 ${escapeHtml(cp.name)}</span>
                            <span class="inbox-cctx-val" style="text-align:right;">${escapeHtml(cp.job_title || 'Member')}</span>
                        </div>
                    `).join('')}
                </div>
            `
            : '';

        return `
            <div class="inbox-cctx-profile">
                <div class="avatar avatar-xl" style="background-color: ${c.avatarBg || '#7C3AED'}; margin: 0 auto 10px;" aria-hidden="true">${escapeHtml(c.avatar)}</div>
                <div class="inbox-cctx-name">${escapeHtml(c.name || c.contact)}</div>
                <div class="inbox-cctx-title">${escapeHtml(c.title || 'Contact')}</div>
                <div class="inbox-cctx-company">${escapeHtml(c.company || 'N/A')}</div>
                <span class="inbox-cctx-badge">${channelLabel}</span>
            </div>

            <div class="inbox-cctx-section">
                <div class="inbox-cctx-section-title">Contact Information</div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Email</span><span class="inbox-cctx-val">${escapeHtml(c.email || 'N/A')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Phone</span><span class="inbox-cctx-val">${escapeHtml(c.phone || 'N/A')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Channel</span><span class="inbox-cctx-val">${channelLabel}</span></div>
            </div>

            <div class="inbox-cctx-section">
                <div class="inbox-cctx-section-title">CRM Information</div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Owner</span><span class="inbox-cctx-val">${escapeHtml(c.owner || 'Unassigned')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Open Deals</span><span class="inbox-cctx-val" style="color:var(--primary);font-weight:600">${escapeHtml(c.deals || 'N/A')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Priority</span><span class="inbox-cctx-val">${escapeHtml(c.priority || 'Normal')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Last Message</span><span class="inbox-cctx-val">${escapeHtml(c.timestamp || 'N/A')}</span></div>
            </div>

            ${assocCompaniesHtml}

            <div class="inbox-cctx-section">
                <div class="inbox-cctx-section-title">Tags</div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;">
                    ${tags.map(t => `<span class="tasks-tab-chip" style="font-size:10px;padding:2px 7px;">${escapeHtml(t)}</span>`).join('')}
                </div>
            </div>

            <div class="inbox-cctx-actions">
                <a href="${c.id ? 'contacts.php?contact_id=' + c.id : 'contacts.php'}" class="btn btn-primary btn-sm" style="width:100%; margin-bottom:6px; text-align:center; display:block;">Open Full Contacts Page</a>
                <div style="display:flex;gap:6px;">
                    <a href="tasks.php" class="btn btn-secondary btn-sm" style="flex:1; text-align:center;">+ Task</a>
                    <button type="button" class="btn btn-secondary btn-sm" style="flex:1;" onclick="window.inboxApp.quickSwitchToNote()">+ Note</button>
                </div>
            </div>
        `;
    }

    function buildEmptyCompanyHtml(convId) {
        return `
            <div style="padding: 36px 16px; text-align: center;">
                <div style="font-size: 38px; margin-bottom: 12px;">🏢</div>
                <div style="font-size: 15px; font-weight: 700; color: var(--text-heading); margin-bottom: 6px;">Company Not Linked</div>
                <div style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.5;">
                    This conversation is not currently linked to a CRM company record.
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.inboxApp.closeCompanyDrawer(); window.inboxApp.openEditRelationshipModal('${convId}');">
                    🔗 Link Company
                </button>
            </div>
        `;
    }

    function buildCompanyDrawerHtml(cp) {
        const contactsList = (cp.contacts && cp.contacts.length > 0)
            ? cp.contacts.map(ct => `
                <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid var(--border-divider, #F3F4F6); font-size:12px;">
                    <div>
                        <div style="font-weight:600; color:var(--text-heading);">${escapeHtml(ct.name)}</div>
                        <div style="font-size:11px; color:var(--text-muted);">${escapeHtml(ct.job_title || ct.email || '')}</div>
                    </div>
                </div>
            `).join('')
            : '<div style="font-size:12px; color:var(--text-muted); padding:4px 0;">No associated contacts found.</div>';

        const dealsList = (cp.deals && cp.deals.length > 0)
            ? cp.deals.map(d => `
                <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px solid var(--border-divider, #F3F4F6); font-size:12px;">
                    <div>
                        <div style="font-weight:600; color:var(--text-heading);">${escapeHtml(d.name)}</div>
                        <div style="font-size:11px; color:var(--text-muted);">${escapeHtml(d.stage || 'Open')}</div>
                    </div>
                    <div style="font-weight:600; color:var(--primary); font-size:12px;">$${parseInt(d.value || 0).toLocaleString()}</div>
                </div>
            `).join('')
            : '<div style="font-size:12px; color:var(--text-muted); padding:4px 0;">No open deals.</div>';

        return `
            <div class="inbox-cctx-profile">
                <div class="avatar avatar-xl" style="background-color: ${cp.avatarBg || '#2563EB'}; margin: 0 auto 10px;" aria-hidden="true">${escapeHtml(cp.initials || 'CO')}</div>
                <div class="inbox-cctx-name">${escapeHtml(cp.name)}</div>
                <div class="inbox-cctx-title">${escapeHtml(cp.industry || 'Company')}</div>
                <span class="inbox-cctx-badge">${escapeHtml(cp.relationship || 'Account')}</span>
            </div>

            <div class="inbox-cctx-section">
                <div class="inbox-cctx-section-title">Company Information</div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Domain</span><span class="inbox-cctx-val">${escapeHtml(cp.domain || '—')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Industry</span><span class="inbox-cctx-val">${escapeHtml(cp.industry || '—')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Size</span><span class="inbox-cctx-val">${escapeHtml(cp.company_size || '—')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Location</span><span class="inbox-cctx-val">${escapeHtml(cp.location || '—')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Owner</span><span class="inbox-cctx-val">${escapeHtml(cp.owner || 'Unassigned')}</span></div>
            </div>

            <div class="inbox-cctx-section">
                <div class="inbox-cctx-section-title">Pipeline & Revenue</div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Pipeline Value</span><span class="inbox-cctx-val" style="color:var(--primary); font-weight:600;">${escapeHtml(cp.pipeline_value || '$0')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Open Deals</span><span class="inbox-cctx-val">${cp.open_deals_cnt || 0}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Ann. Revenue</span><span class="inbox-cctx-val">${escapeHtml(cp.revenue || '—')}</span></div>
            </div>

            <div class="inbox-cctx-section">
                <div class="inbox-cctx-section-title">Associated Contacts</div>
                <div style="max-height:140px; overflow-y:auto;">
                    ${contactsList}
                </div>
            </div>

            <div class="inbox-cctx-section">
                <div class="inbox-cctx-section-title">Open Deals</div>
                <div style="max-height:140px; overflow-y:auto;">
                    ${dealsList}
                </div>
            </div>

            <div class="inbox-cctx-actions">
                <a href="companies.php?id=${cp.id}" class="btn btn-primary btn-sm" style="width:100%; margin-bottom:6px; text-align:center; display:block;">Open Full Companies Page</a>
                <div style="display:flex;gap:6px;">
                    <a href="tasks.php" class="btn btn-secondary btn-sm" style="flex:1; text-align:center;">+ Task</a>
                    <button type="button" class="btn btn-secondary btn-sm" style="flex:1;" onclick="window.inboxApp.quickSwitchToNote()">+ Note</button>
                </div>
            </div>
        `;
    }

    function buildEmptyDealHtml(convId) {
        return `
            <div style="padding: 36px 16px; text-align: center;">
                <div style="font-size: 38px; margin-bottom: 12px;">💼</div>
                <div style="font-size: 15px; font-weight: 700; color: var(--text-heading); margin-bottom: 6px;">Deal Not Linked</div>
                <div style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 20px; line-height: 1.5;">
                    This conversation is not currently linked to an active CRM deal.
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.inboxApp.closeDealDrawer(); window.inboxApp.openEditRelationshipModal('${convId}');">
                    🔗 Link Deal
                </button>
            </div>
        `;
    }

    function buildDealDrawerHtml(d) {
        return `
            <div class="inbox-cctx-profile">
                <div class="avatar avatar-xl" style="background-color: #059669; margin: 0 auto 10px;" aria-hidden="true">💼</div>
                <div class="inbox-cctx-name">${escapeHtml(d.name)}</div>
                <div class="inbox-cctx-title" style="font-size:18px; font-weight:700; color:var(--primary); margin:4px 0;">${escapeHtml(d.formatted_value || '$0')}</div>
                <span class="inbox-cctx-badge" style="background-color:#D1FAE5; color:#065F46;">${escapeHtml(d.stage || 'Open')}</span>
            </div>

            <div class="inbox-cctx-section">
                <div class="inbox-cctx-section-title">Deal Details</div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Stage</span><span class="inbox-cctx-val">${escapeHtml(d.stage || '—')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Probability</span><span class="inbox-cctx-val">${escapeHtml(d.probability || '—')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Expected Close</span><span class="inbox-cctx-val">${escapeHtml(d.expected_close_date || '—')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Status</span><span class="inbox-cctx-val" style="text-transform:capitalize;">${escapeHtml(d.status || 'open')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Tag</span><span class="inbox-cctx-val">${escapeHtml(d.tag || 'General')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Owner</span><span class="inbox-cctx-val">${escapeHtml(d.owner || 'Unassigned')}</span></div>
            </div>

            <div class="inbox-cctx-section">
                <div class="inbox-cctx-section-title">Associated Records</div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Contact</span><span class="inbox-cctx-val">${escapeHtml(d.contact || '—')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Company</span><span class="inbox-cctx-val">${escapeHtml(d.company || '—')}</span></div>
                <div class="inbox-cctx-row"><span class="inbox-cctx-label">Created</span><span class="inbox-cctx-val">${escapeHtml(d.created_at || '—')}</span></div>
            </div>

            <div class="inbox-cctx-actions">
                <a href="pipeline.php?deal_id=${d.id}" class="btn btn-primary btn-sm" style="width:100%; margin-bottom:6px; text-align:center; display:block;">Open Full Pipeline Page</a>
                <div style="display:flex;gap:6px;">
                    <a href="tasks.php" class="btn btn-secondary btn-sm" style="flex:1; text-align:center;">+ Task</a>
                    <button type="button" class="btn btn-secondary btn-sm" style="flex:1;" onclick="window.inboxApp.quickSwitchToNote()">+ Note</button>
                </div>
            </div>
        `;
    }

    // Public Inbox App Object
    window.inboxApp = {
        selectConversation: function(id) {
            activeConvId = id;

            // Find existing summary
            const c = allConversations.find(item => String(item.id) === String(id));

            // Mark read on open if enabled
            const markReadPref = window._inboxPrefs ? window._inboxPrefs.markReadOnOpen : true;
            if (c && c.unread && markReadPref) {
                c.unread = false;
                const fd = new FormData();
                fd.append('action', 'toggle_read');
                fd.append('id', id);
                fd.append('is_unread', '0');
                fetch('/nexFlow/admin/api/inbox.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(() => loadCounts())
                    .catch(err => console.error("Toggle read failed:", err));
            }

            applyInboxLayoutState();
            renderConvList();

            // Fetch live thread messages from MySQL
            fetch(`/nexFlow/admin/api/inbox.php?action=thread&id=${id}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data && res.data.conversation) {
                    renderActiveThread(res.data.conversation);
                } else {
                    renderActiveThread(c);
                }
            })
            .catch(err => {
                console.error("Failed to load thread:", err);
                renderActiveThread(c);
            });

            // Mobile layout
            const listPanel = document.querySelector(".inbox-list-panel");
            const threadPanel = document.querySelector(".inbox-thread-panel");
            if (window.innerWidth <= 768) {
                if (listPanel) listPanel.style.display = "none";
                if (threadPanel) threadPanel.classList.add("mobile-show");
            }
        },

        closeThread: function() {
            const prevConvId = activeConvId;
            activeConvId = null;
            applyInboxLayoutState();
            renderConvList();
            renderActiveThread();

            const listPanel = document.querySelector(".inbox-list-panel");
            const threadPanel = document.querySelector(".inbox-thread-panel");
            if (window.innerWidth <= 768) {
                if (listPanel) listPanel.style.display = "";
                if (threadPanel) threadPanel.classList.remove("mobile-show");
            }

            if (prevConvId) {
                const prevRow = document.querySelector(`.inbox-conv-item[data-conv-id="${prevConvId}"]`);
                if (prevRow) prevRow.focus();
            }
        },

        openContactDrawer: function(targetId, triggerElement) {
            lastFocusedElement = triggerElement || document.activeElement;

            const drawer = document.getElementById("inboxContactDrawer");
            const panel = document.getElementById("inboxContactPanel");
            const nameEl = document.getElementById("inboxContactDrawerName");
            const bodyEl = document.getElementById("inboxContactDrawerBody");

            if (!drawer || !panel) return;

            // Resolve conversation & contact
            const c = allConversations.find(item => String(item.id) === String(targetId) || String(item.contact_id) === String(targetId))
                   || (activeConvId ? allConversations.find(item => String(item.id) === String(activeConvId)) : null);

            const convId = c ? c.id : (activeConvId || targetId);
            const contactId = (c && c.contact_id) ? c.contact_id : (targetId && String(targetId) !== String(convId) ? targetId : null);

            activeContactId = contactId || convId;

            if (nameEl) nameEl.textContent = (c && c.contact) ? c.contact : 'Contact Details';
            if (bodyEl) bodyEl.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);">Loading CRM contact details...</div>';

            drawer.classList.add("show");
            drawer.setAttribute("aria-hidden", "false");

            const closeBtn = document.getElementById("inboxContactDrawerClose");
            if (closeBtn) closeBtn.focus();
            document.body.style.overflow = "hidden";

            if (!contactId && (!c || !c.contact_id)) {
                if (nameEl) nameEl.textContent = 'Contact Details';
                if (bodyEl) bodyEl.innerHTML = buildEmptyContactHtml(convId);
                return;
            }

            // Fetch live contact context from MySQL
            const apiUrl = contactId
                ? `/nexFlow/admin/api/inbox.php?action=contact_context&contact_id=${encodeURIComponent(contactId)}`
                : `/nexFlow/admin/api/inbox.php?action=contact_context&conversation_id=${encodeURIComponent(convId)}`;

            fetch(apiUrl, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data && res.data.context) {
                    if (nameEl) nameEl.textContent = res.data.context.name;
                    if (bodyEl) bodyEl.innerHTML = buildContactContextHtml(res.data.context);
                } else if (res.not_linked || (res.data && res.data.not_linked)) {
                    if (nameEl) nameEl.textContent = 'Contact Details';
                    if (bodyEl) bodyEl.innerHTML = buildEmptyContactHtml(convId);
                } else if (c && c.contact_id) {
                    if (bodyEl) bodyEl.innerHTML = buildContactContextHtml(c);
                } else {
                    if (nameEl) nameEl.textContent = 'Contact Details';
                    if (bodyEl) bodyEl.innerHTML = buildEmptyContactHtml(convId);
                }
            })
            .catch(() => {
                if (bodyEl) bodyEl.innerHTML = buildEmptyContactHtml(convId);
            });
        },

        openContactContext: function(convId, triggerElement) {
            this.openContactDrawer(convId, triggerElement);
        },

        closeContactDrawer: function() {
            const drawer = document.getElementById("inboxContactDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
            }
            activeContactId = null;
            document.body.style.overflow = "";
            restoreKeyboardFocus();
        },

        openCompanyDrawer: function(convId, triggerElement) {
            lastFocusedElement = triggerElement || document.activeElement;

            const drawer = document.getElementById("inboxCompanyDrawer");
            const nameEl = document.getElementById("inboxCompanyDrawerName");
            const bodyEl = document.getElementById("inboxCompanyDrawerBody");

            if (!drawer) return;

            const c = (activeThreadConversation && String(activeThreadConversation.id) === String(convId))
                   ? activeThreadConversation
                   : allConversations.find(item => String(item.id) === String(convId));

            if (!c || !c.company_id) {
                if (nameEl) nameEl.textContent = 'Company Details';
                if (bodyEl) bodyEl.innerHTML = buildEmptyCompanyHtml(convId);
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
                const closeBtn = document.getElementById("inboxCompanyDrawerClose");
                if (closeBtn) closeBtn.focus();
                document.body.style.overflow = "hidden";
                return;
            }

            if (nameEl) nameEl.textContent = (c && c.company && c.company !== 'N/A') ? c.company : 'Company Details';
            if (bodyEl) bodyEl.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);">Loading CRM company details...</div>';

            drawer.classList.add("show");
            drawer.setAttribute("aria-hidden", "false");

            const closeBtn = document.getElementById("inboxCompanyDrawerClose");
            if (closeBtn) closeBtn.focus();
            document.body.style.overflow = "hidden";

            fetch(`/nexFlow/admin/api/inbox.php?action=company_detail&company_id=${encodeURIComponent(c.company_id)}&conversation_id=${encodeURIComponent(convId)}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data && res.data.company) {
                    if (nameEl) nameEl.textContent = res.data.company.name;
                    if (bodyEl) bodyEl.innerHTML = buildCompanyDrawerHtml(res.data.company);
                } else {
                    if (nameEl) nameEl.textContent = 'Company Details';
                    if (bodyEl) bodyEl.innerHTML = buildEmptyCompanyHtml(convId);
                }
            })
            .catch(() => {
                if (bodyEl) bodyEl.innerHTML = buildEmptyCompanyHtml(convId);
            });
        },

        closeCompanyDrawer: function() {
            const drawer = document.getElementById("inboxCompanyDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
            }
            document.body.style.overflow = "";
            restoreKeyboardFocus();
        },

        openDealDrawer: function(convId, triggerElement) {
            lastFocusedElement = triggerElement || document.activeElement;

            const drawer = document.getElementById("inboxDealDrawer");
            const nameEl = document.getElementById("inboxDealDrawerName");
            const bodyEl = document.getElementById("inboxDealDrawerBody");

            if (!drawer) return;

            const c = (activeThreadConversation && String(activeThreadConversation.id) === String(convId))
                   ? activeThreadConversation
                   : allConversations.find(item => String(item.id) === String(convId));

            if (!c || !c.deal_id) {
                if (nameEl) nameEl.textContent = 'Deal Details';
                if (bodyEl) bodyEl.innerHTML = buildEmptyDealHtml(convId);
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
                const closeBtn = document.getElementById("inboxDealDrawerClose");
                if (closeBtn) closeBtn.focus();
                document.body.style.overflow = "hidden";
                return;
            }

            if (nameEl) nameEl.textContent = (c && c.deals && c.deals !== 'N/A') ? c.deals : 'Deal Details';
            if (bodyEl) bodyEl.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);">Loading CRM deal details...</div>';

            drawer.classList.add("show");
            drawer.setAttribute("aria-hidden", "false");

            const closeBtn = document.getElementById("inboxDealDrawerClose");
            if (closeBtn) closeBtn.focus();
            document.body.style.overflow = "hidden";

            fetch(`/nexFlow/admin/api/inbox.php?action=deal_detail&deal_id=${encodeURIComponent(c.deal_id)}&conversation_id=${encodeURIComponent(convId)}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data && res.data.deal) {
                    if (nameEl) nameEl.textContent = res.data.deal.name;
                    if (bodyEl) bodyEl.innerHTML = buildDealDrawerHtml(res.data.deal);
                } else {
                    if (nameEl) nameEl.textContent = 'Deal Details';
                    if (bodyEl) bodyEl.innerHTML = buildEmptyDealHtml(convId);
                }
            })
            .catch(() => {
                if (bodyEl) bodyEl.innerHTML = buildEmptyDealHtml(convId);
            });
        },

        closeDealDrawer: function() {
            const drawer = document.getElementById("inboxDealDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
            }
            document.body.style.overflow = "";
            restoreKeyboardFocus();
        },

        quickSwitchToNote: function() {
            this.closeContactDrawer();
            this.switchComposerTab('note');
            const ta = document.getElementById("noteTextarea");
            if (ta) ta.focus();
        },

        setFolder: function(folder, elem) {
            activeFolder = folder;
            activeTeam = "All";
            document.querySelectorAll(".inbox-nav .inbox-folder-item").forEach(item => item.classList.remove("active"));
            if (elem) elem.classList.add("active");
            loadConversations(false);
        },

        setChannelFilter: function(channel, elem) {
            activeChannel = channel;
            activeTeam = "All";
            document.querySelectorAll(".inbox-nav .inbox-folder-item").forEach(item => item.classList.remove("active"));
            if (elem) elem.classList.add("active");
            loadConversations(false);
        },

        setTeamFilter: function(team, elem) {
            activeTeam = team;
            activeFolder = "all";
            document.querySelectorAll(".inbox-nav .inbox-folder-item").forEach(item => item.classList.remove("active"));
            if (elem) elem.classList.add("active");
            loadConversations(false);
        },

        setSort: function(val) {
            sortBy = val;
            loadConversations(true);
        },

        toggleStar: function(id, e) {
            if (e) e.stopPropagation();
            const c = allConversations.find(item => String(item.id) === String(id));
            const newStarState = c ? !c.starred : true;

            if (c) c.starred = newStarState;
            renderConvList();

            const fd = new FormData();
            fd.append('action', 'toggle_star');
            fd.append('id', id);
            fd.append('is_starred', newStarState ? '1' : '0');

            fetch('/nexFlow/admin/api/inbox.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showInboxToast(newStarState ? "Conversation starred." : "Star removed.");
                        loadCounts();
                    }
                })
                .catch(err => console.error("Toggle star failed:", err));
        },

        changeStatus: function(id, statusVal) {
            const fd = new FormData();
            fd.append('action', 'update_status');
            fd.append('id', id);
            fd.append('status', statusVal);

            fetch('/nexFlow/admin/api/inbox.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showInboxToast(`Status updated to '${statusVal}'.`);
                        loadConversations(true);
                        loadCounts();
                    } else {
                        showInboxToast(res.message || "Failed to update status.");
                    }
                })
                .catch(err => {
                    console.error("Status update error:", err);
                    showInboxToast("Failed to update status.");
                });
        },

        archiveConversation: function(id) {
            const fd = new FormData();
            fd.append('action', 'archive');
            fd.append('id', id);

            fetch('/nexFlow/admin/api/inbox.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showInboxToast("Conversation archived.");
                        if (String(activeConvId) === String(id) && activeFolder !== "archived") {
                            activeConvId = null;
                            applyInboxLayoutState();
                            renderActiveThread();
                        }
                        loadConversations(false);
                        loadCounts();
                    }
                })
                .catch(err => console.error("Archive error:", err));
        },

        unarchiveConversation: function(id) {
            const fd = new FormData();
            fd.append('action', 'unarchive');
            fd.append('id', id);

            fetch('/nexFlow/admin/api/inbox.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showInboxToast("Conversation restored.");
                        loadConversations(true);
                        loadCounts();
                    }
                })
                .catch(err => console.error("Unarchive error:", err));
        },

        snoozeConversation: function(id, until = '') {
            const fd = new FormData();
            fd.append('action', 'snooze');
            fd.append('id', id);
            if (until) fd.append('until', until);

            fetch('/nexFlow/admin/api/inbox.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showInboxToast("Conversation snoozed.");
                        if (String(activeConvId) === String(id) && activeFolder !== "snoozed") {
                            activeConvId = null;
                            applyInboxLayoutState();
                            renderActiveThread();
                        }
                        loadConversations(false);
                        loadCounts();
                    }
                })
                .catch(err => console.error("Snooze error:", err));
        },

        switchComposerTab: function(tab) {
            const c = allConversations.find(item => String(item.id) === String(activeConvId));
            if (!c) return;

            const tabReply = document.getElementById("tabCompReply");
            const tabNote  = document.getElementById("tabCompNote");
            if (tabReply) tabReply.classList.toggle("active", tab === "reply");
            if (tabNote)  tabNote.classList.toggle("active", tab === "note");

            const area = document.getElementById("composerBodyArea");
            if (!area) return;

            if (tab === "note") {
                area.innerHTML = `
                    <textarea class="input-control" id="noteTextarea" style="height: 60px; font-size: 12.5px; resize: none; margin-bottom: 8px; background-color: #FEF0C7; border-color: #F79009;" placeholder="Write an internal team note..."></textarea>
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 10.5px; color: #7A2E0E;">Internal notes are recorded in CRM history and hidden from clients.</span>
                        <button type="button" class="btn btn-primary btn-xs" style="background-color: #F79009; border-color: #F79009;" onclick="window.inboxApp.addInternalNote('${c.id}')">Add Internal Note</button>
                    </div>
                `;
            } else {
                area.innerHTML = `
                    <textarea class="input-control" id="replyTextarea" style="height: 60px; font-size: 12.5px; resize: none; margin-bottom: 8px;" placeholder="Write your reply to ${escapeHtml(c.contact)}..."></textarea>
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 10.5px; color: var(--text-muted);">Pressing send will record the message in CRM history${c.channel === 'WhatsApp' ? ' and open wa.me' : ' and open your email client'}.</span>
                        <button type="button" class="btn btn-primary btn-xs" onclick="window.inboxApp.sendReply('${c.id}')">${c.channel === 'WhatsApp' ? 'Continue to WhatsApp' : 'Send & Open Email'}</button>
                    </div>
                `;
            }
        },

        sendReply: function(id) {
            const c = allConversations.find(item => String(item.id) === String(id));
            if (!c) return;

            const textarea = document.getElementById("replyTextarea");
            const text = textarea ? textarea.value.trim() : "";

            if (!text) {
                showInboxToast("Please enter a reply message.");
                if (textarea) textarea.focus();
                return;
            }

            const fd = new FormData();
            fd.append('action', 'reply');
            fd.append('id', id);
            fd.append('body', text);

            fetch('/nexFlow/admin/api/inbox.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        if (textarea) textarea.value = "";

                        // If external protocol integration requested
                        if (c.channel === "WhatsApp") {
                            const phone = (c.phone || "").replace(/[^0-9]/g, "");
                            showInboxToast("Reply saved to CRM. Opening WhatsApp...");
                            setTimeout(() => {
                                window.open(`https://wa.me/${phone}?text=${encodeURIComponent(text)}`, "_blank");
                            }, 300);
                        } else {
                            showInboxToast("Reply saved to CRM. Opening email client...");
                            setTimeout(() => {
                                window.location.href = `mailto:${c.email}?subject=${encodeURIComponent("Re: " + c.subject)}&body=${encodeURIComponent(text)}`;
                            }, 300);
                        }

                        // Re-fetch thread to show new message
                        window.inboxApp.selectConversation(id);
                        loadConversations(true);
                        loadCounts();
                    } else {
                        showInboxToast(res.message || "Failed to save reply.");
                    }
                })
                .catch(err => {
                    console.error("Reply error:", err);
                    showInboxToast("Failed to save reply.");
                });
        },

        addInternalNote: function(id) {
            const c = allConversations.find(item => String(item.id) === String(id));
            if (!c) return;

            const textarea = document.getElementById("noteTextarea");
            const text = textarea ? textarea.value.trim() : "";
            if (!text) {
                showInboxToast("Please enter note content.");
                if (textarea) textarea.focus();
                return;
            }

            const fd = new FormData();
            fd.append('action', 'internal_note');
            fd.append('id', id);
            fd.append('body', text);

            fetch('/nexFlow/admin/api/inbox.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        if (textarea) textarea.value = "";
                        showInboxToast("Internal note logged.");
                        window.inboxApp.selectConversation(id);
                        loadConversations(true);
                    } else {
                        showInboxToast(res.message || "Failed to add note.");
                    }
                })
                .catch(err => {
                    console.error("Note error:", err);
                    showInboxToast("Failed to add note.");
                });
        },

        openComposeDrawer: function(isNewDraft = true) {
            lastFocusedElement = document.activeElement;
            const form = document.getElementById("composeMessageForm");
            if (form) form.reset();

            if (isNewDraft) {
                const draftInput = document.getElementById("composeDraftId");
                if (draftInput) draftInput.value = "";
                const titleEl = document.getElementById("composeModalTitle");
                if (titleEl) titleEl.textContent = "New Message";
            }

            if (window._crmLookups) {
                populateCrmLookupsDropdowns(window._crmLookups);
            }

            window.inboxApp.toggleComposeChannelFields(document.getElementById("composeChannelSelect").value || 'Email');

            const drawer = document.getElementById("inboxComposeDrawer");
            if (drawer) {
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
                const firstInput = document.getElementById("composeToInput");
                if (firstInput) firstInput.focus();
            }

            document.body.style.overflow = "hidden";
        },

        closeComposeDrawer: function() {
            const drawer = document.getElementById("inboxComposeDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
            }
            document.body.style.overflow = "";
            restoreKeyboardFocus();
        },

        saveDraft: function() {
            const channel   = document.getElementById("composeChannelSelect").value;
            const to        = document.getElementById("composeToInput").value.trim();
            const subject   = document.getElementById("composeSubjectInput") ? document.getElementById("composeSubjectInput").value.trim() : "";
            const body      = document.getElementById("composeBodyInput").value.trim();
            const contactId = document.getElementById("composeContactSelect") ? document.getElementById("composeContactSelect").value : "";
            const companyId = document.getElementById("composeCompanySelect") ? document.getElementById("composeCompanySelect").value : "";
            const dealId    = document.getElementById("composeDealSelect") ? document.getElementById("composeDealSelect").value : "";
            const draftId   = document.getElementById("composeDraftId") ? document.getElementById("composeDraftId").value : "";

            const btn = document.getElementById("composeSaveDraftBtn");
            if (btn) {
                btn.disabled = true;
                btn.textContent = "Saving...";
            }

            const fd = new FormData();
            fd.append('action', 'save_draft');
            if (draftId) fd.append('draft_id', draftId);
            fd.append('channel', channel);
            fd.append('to', to);
            fd.append('subject', subject);
            fd.append('body', body);
            if (contactId) fd.append('contact_id', contactId);
            if (companyId) fd.append('company_id', companyId);
            if (dealId) fd.append('deal_id', dealId);

            fetch('/nexFlow/admin/api/inbox.php', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(res => {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Save Draft";
                }

                if (res.success && res.data && res.data.draft_id) {
                    document.getElementById("composeDraftId").value = res.data.draft_id;
                    const titleEl = document.getElementById("composeModalTitle");
                    if (titleEl) titleEl.textContent = "Edit Draft";
                    showInboxToast("Draft saved.");
                    loadCounts();
                    if (activeFolder === "drafts") {
                        loadConversations(false);
                    }
                } else {
                    showInboxToast(res.message || "Failed to save draft.");
                }
            })
            .catch(err => {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = "Save Draft";
                }
                console.error("Save draft error:", err);
                showInboxToast("Error saving draft.");
            });
        },

        openDraftInCompose: function(draftId) {
            fetch(`/nexFlow/admin/api/inbox.php?action=load_draft&id=${encodeURIComponent(draftId)}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data && res.data.draft) {
                    const d = res.data.draft;
                    const form = document.getElementById("composeMessageForm");
                    if (form) form.reset();

                    const draftInput = document.getElementById("composeDraftId");
                    if (draftInput) draftInput.value = d.id;

                    const titleEl = document.getElementById("composeModalTitle");
                    if (titleEl) titleEl.textContent = "Edit Draft";

                    const chSel = document.getElementById("composeChannelSelect");
                    if (chSel) chSel.value = d.channel || 'Email';

                    const toInput = document.getElementById("composeToInput");
                    if (toInput) toInput.value = d.recipient || '';

                    const subjInput = document.getElementById("composeSubjectInput");
                    if (subjInput) subjInput.value = d.subject || '';

                    const bodyInput = document.getElementById("composeBodyInput");
                    if (bodyInput) bodyInput.value = d.body || '';

                    if (window._crmLookups) {
                        populateCrmLookupsDropdowns(window._crmLookups);
                    }

                    const ctSel = document.getElementById("composeContactSelect");
                    if (ctSel && d.contact_id) {
                        ctSel.value = d.contact_id;
                        onContactSelectionChange(d.contact_id, "composeCompanySelect", "composeDealSelect");
                        setTimeout(() => {
                            if (d.company_id) {
                                const cpSel = document.getElementById("composeCompanySelect");
                                if (cpSel) cpSel.value = d.company_id;
                            }
                            if (d.deal_id) {
                                const dSel = document.getElementById("composeDealSelect");
                                if (dSel) dSel.value = d.deal_id;
                            }
                        }, 200);
                    } else {
                        if (d.company_id) {
                            const cpSel = document.getElementById("composeCompanySelect");
                            if (cpSel) cpSel.value = d.company_id;
                        }
                        if (d.deal_id) {
                            const dSel = document.getElementById("composeDealSelect");
                            if (dSel) dSel.value = d.deal_id;
                        }
                    }

                    window.inboxApp.toggleComposeChannelFields(d.channel || 'Email');

                    const drawer = document.getElementById("inboxComposeDrawer");
                    if (drawer) {
                        drawer.classList.add("show");
                        drawer.setAttribute("aria-hidden", "false");
                    }
                    document.body.style.overflow = "hidden";
                } else {
                    showInboxToast(res.message || "Failed to load draft.");
                }
            })
            .catch(err => {
                console.error("Load draft error:", err);
                showInboxToast("Failed to load draft.");
            });
        },

        onComposeContactChange: function(contactId) {
            if (contactId && window._crmLookups && Array.isArray(window._crmLookups.contacts)) {
                const found = window._crmLookups.contacts.find(c => String(c.id) === String(contactId));
                if (found) {
                    const toInput = document.getElementById("composeToInput");
                    if (toInput && !toInput.value) {
                        toInput.value = found.email || found.phone || '';
                    }
                }
            }
            onContactSelectionChange(contactId, "composeCompanySelect", "composeDealSelect");
        },

        openSnoozeModal: function(convId) {
            const modal = document.getElementById("inboxSnoozeModal");
            const idInput = document.getElementById("snoozeConvId");
            if (modal && idInput) {
                idInput.value = convId;
                modal.style.display = "flex";
                modal.classList.add("show");
                modal.setAttribute("aria-hidden", "false");
                document.body.style.overflow = "hidden";
            }
        },

        closeSnoozeModal: function() {
            const modal = document.getElementById("inboxSnoozeModal");
            if (modal) {
                modal.style.display = "none";
                modal.classList.remove("show");
                modal.setAttribute("aria-hidden", "true");
                document.body.style.overflow = "";
            }
        },

        applyPresetSnooze: function(preset) {
            const convId = document.getElementById("snoozeConvId").value;
            if (!convId) return;

            const fd = new FormData();
            fd.append('action', 'snooze');
            fd.append('id', convId);
            fd.append('preset', preset);

            fetch('/nexFlow/admin/api/inbox.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    window.inboxApp.closeSnoozeModal();
                    if (res.success) {
                        showInboxToast(`Conversation snoozed until ${res.data && res.data.formatted ? res.data.formatted : 'scheduled time'}.`);
                        if (String(activeConvId) === String(convId) && activeFolder !== "snoozed") {
                            activeConvId = null;
                            applyInboxLayoutState();
                            renderActiveThread();
                        } else if (String(activeConvId) === String(convId)) {
                            window.inboxApp.selectConversation(convId);
                        }
                        loadConversations(false);
                        loadCounts();
                    } else {
                        showInboxToast(res.message || "Failed to snooze conversation.");
                    }
                })
                .catch(err => {
                    console.error("Snooze error:", err);
                    showInboxToast("Failed to snooze conversation.");
                });
        },

        applyCustomSnooze: function() {
            const convId = document.getElementById("snoozeConvId").value;
            const customVal = document.getElementById("snoozeCustomInput").value;

            if (!customVal) {
                showInboxToast("Please select a date and time.");
                return;
            }

            const customTs = new Date(customVal).getTime();
            if (isNaN(customTs) || customTs <= Date.now()) {
                showInboxToast("Snooze date and time must be in the future.");
                return;
            }

            const fd = new FormData();
            fd.append('action', 'snooze');
            fd.append('id', convId);
            fd.append('until', customVal);

            fetch('/nexFlow/admin/api/inbox.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    window.inboxApp.closeSnoozeModal();
                    if (res.success) {
                        showInboxToast(`Conversation snoozed until ${res.data && res.data.formatted ? res.data.formatted : 'scheduled time'}.`);
                        if (String(activeConvId) === String(convId) && activeFolder !== "snoozed") {
                            activeConvId = null;
                            applyInboxLayoutState();
                            renderActiveThread();
                        } else if (String(activeConvId) === String(convId)) {
                            window.inboxApp.selectConversation(convId);
                        }
                        loadConversations(false);
                        loadCounts();
                    } else {
                        showInboxToast(res.message || "Failed to snooze conversation.");
                    }
                })
                .catch(err => {
                    console.error("Snooze error:", err);
                    showInboxToast("Failed to snooze conversation.");
                });
        },

        unsnoozeConversation: function(convId) {
            const fd = new FormData();
            fd.append('action', 'unsnooze');
            fd.append('id', convId);

            fetch('/nexFlow/admin/api/inbox.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showInboxToast("Conversation restored to active.");
                        if (String(activeConvId) === String(convId) && activeFolder === "snoozed") {
                            activeConvId = null;
                            applyInboxLayoutState();
                            renderActiveThread();
                        } else if (String(activeConvId) === String(convId)) {
                            window.inboxApp.selectConversation(convId);
                        }
                        loadConversations(false);
                        loadCounts();
                    } else {
                        showInboxToast(res.message || "Failed to unsnooze conversation.");
                    }
                })
                .catch(err => {
                    console.error("Unsnooze error:", err);
                    showInboxToast("Failed to unsnooze conversation.");
                });
        },

        openEditRelationshipModal: function(convId) {
            const modal = document.getElementById("inboxEditRelationshipModal");
            const idInput = document.getElementById("editRelConvId");
            if (!modal || !idInput) return;

            idInput.value = convId;
            const c = (activeThreadConversation && String(activeThreadConversation.id) === String(convId))
                ? activeThreadConversation
                : allConversations.find(item => String(item.id) === String(convId));

            const populateAndSet = () => {
                const ctSel = document.getElementById("editRelContactSelect");
                const cpSel = document.getElementById("editRelCompanySelect");
                const dSel  = document.getElementById("editRelDealSelect");

                if (window._crmLookups) {
                    populateCrmLookupsDropdowns(window._crmLookups);
                }

                if (ctSel) ctSel.value = (c && c.contact_id) ? String(c.contact_id) : "";
                if (cpSel) cpSel.value = (c && c.company_id) ? String(c.company_id) : "";
                if (dSel)  dSel.value  = (c && c.deal_id)    ? String(c.deal_id)    : "";
            };

            if (!window._crmLookups) {
                loadCrmLookups();
                setTimeout(populateAndSet, 300);
            } else {
                populateAndSet();
            }

            // Verify current database values from backend for the conversation
            fetch(`/nexFlow/admin/api/inbox.php?action=thread&id=${encodeURIComponent(convId)}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data && res.data.conversation) {
                    const conv = res.data.conversation;
                    const ctSel = document.getElementById("editRelContactSelect");
                    const cpSel = document.getElementById("editRelCompanySelect");
                    const dSel  = document.getElementById("editRelDealSelect");
                    if (ctSel && conv.contact_id !== undefined) ctSel.value = conv.contact_id ? String(conv.contact_id) : "";
                    if (cpSel && conv.company_id !== undefined) cpSel.value = conv.company_id ? String(conv.company_id) : "";
                    if (dSel  && conv.deal_id    !== undefined) dSel.value  = conv.deal_id    ? String(conv.deal_id)    : "";
                }
            })
            .catch(err => console.error("Prefill DB fetch error:", err));

            modal.style.display = "flex";
            modal.classList.add("show");
            modal.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";
        },

        closeEditRelationshipModal: function() {
            const modal = document.getElementById("inboxEditRelationshipModal");
            if (modal) {
                modal.style.display = "none";
                modal.classList.remove("show");
                modal.setAttribute("aria-hidden", "true");
                document.body.style.overflow = "";
            }
        },

        onEditRelContactChange: function(contactId) {
            // Intentionally no-op: Contact, Company, and Deal are independent relationship fields.
            // Explicit user selection in each dropdown controls its own field.
        },

        saveRelationship: function() {
            const convId    = document.getElementById("editRelConvId").value;
            const contactId = document.getElementById("editRelContactSelect").value;
            const companyId = document.getElementById("editRelCompanySelect").value;
            const dealId    = document.getElementById("editRelDealSelect").value;

            if (!convId) return;

            const fd = new FormData();
            fd.append('action', 'update_relationship');
            fd.append('conversation_id', convId);
            fd.append('contact_id', contactId || '');
            fd.append('company_id', companyId || '');
            fd.append('deal_id', dealId || '');

            fetch('/nexFlow/admin/api/inbox.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    window.inboxApp.closeEditRelationshipModal();
                    if (res.success) {
                        showInboxToast("CRM relationships updated.");
                        loadConversations(true);
                        if (String(activeConvId) === String(convId)) {
                            window.inboxApp.selectConversation(convId);
                        }
                    } else {
                        showInboxToast(res.message || "Failed to update relationships.");
                    }
                })
                .catch(err => {
                    console.error("Save relationship error:", err);
                    showInboxToast("Failed to update relationships.");
                });
        },

        toggleComposeChannelFields: function(val) {
            const subjGroup = document.getElementById("composeSubjectGroup");
            const btn = document.getElementById("composeSubmitBtn");

            if (val === "WhatsApp") {
                if (subjGroup) subjGroup.style.display = "none";
                if (btn) btn.textContent = "Send & Open WhatsApp";
            } else if (val === "Internal Note") {
                if (subjGroup) subjGroup.style.display = "none";
                if (btn) btn.textContent = "Save Note";
            } else {
                if (subjGroup) subjGroup.style.display = "block";
                if (btn) btn.textContent = "Send & Open Email";
            }
        },

        openFilterDrawer: function() {
            lastFocusedElement = document.activeElement;
            const panel = document.getElementById("inboxFilterDrawer");
            if (panel) {
                panel.classList.add("show");
                panel.setAttribute("aria-hidden", "false");
            }
        },

        closeFilterDrawer: function() {
            const panel = document.getElementById("inboxFilterDrawer");
            if (panel) {
                panel.classList.remove("show");
                panel.setAttribute("aria-hidden", "true");
            }
            restoreKeyboardFocus();
        },

        applyMobileFilters: function() {
            const ch = document.getElementById("filterChannelSelect").value;
            activeChannel = ch;
            window.inboxApp.closeFilterDrawer();
            loadConversations(false);
        },

        resetFilters: function() {
            activeFolder = "all";
            activeChannel = "All";
            activeTeam = "All";
            searchQuery = "";
            const searchInput = document.getElementById("inboxSearchInput");
            if (searchInput) searchInput.value = "";
            window.inboxApp.closeFilterDrawer();
            loadConversations(false);
        },

        refreshConversations: function() {
            loadConversations(true);
            loadCounts();
            showInboxToast("Inbox refreshed.");
        }
    };

    function loadCrmLookups() {
        fetch('/nexFlow/admin/api/inbox.php?action=crm_lookups', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                window._crmLookups = res.data;
                populateCrmLookupsDropdowns(res.data);
            }
        })
        .catch(err => console.error("Failed to load CRM lookups:", err));
    }

    function populateCrmLookupsDropdowns(data) {
        const ctSel = document.getElementById("composeContactSelect");
        const cpSel = document.getElementById("composeCompanySelect");
        const dSel  = document.getElementById("composeDealSelect");

        const editCt = document.getElementById("editRelContactSelect");
        const editCp = document.getElementById("editRelCompanySelect");
        const editD  = document.getElementById("editRelDealSelect");

        const renderOptions = (items, placeholder, valKey = 'id', labelKey = 'name') => {
            let html = `<option value="">${placeholder}</option>`;
            if (Array.isArray(items)) {
                items.forEach(item => {
                    const label = item[labelKey] || item.name || item.id;
                    const extra = item.value ? ` ($${parseInt(item.value).toLocaleString()})` : '';
                    html += `<option value="${item[valKey]}">${escapeHtml(label + extra)}</option>`;
                });
            }
            return html;
        };

        if (ctSel) ctSel.innerHTML = renderOptions(data.contacts, 'Select Contact (Optional)...');
        if (cpSel) cpSel.innerHTML = renderOptions(data.companies, 'Select Company (Optional)...');
        if (dSel)  dSel.innerHTML  = renderOptions(data.deals, 'Select Deal (Optional)...');

        if (editCt) editCt.innerHTML = renderOptions(data.contacts, '-- None --');
        if (editCp) editCp.innerHTML = renderOptions(data.companies, '-- None --');
        if (editD)  editD.innerHTML  = renderOptions(data.deals, '-- None --');
    }

    function onContactSelectionChange(contactId, companySelectId, dealSelectId, preferredCompanyId = null, preferredDealId = null) {
        const cpSel = document.getElementById(companySelectId);
        const dSel  = document.getElementById(dealSelectId);
        if (!cpSel) return;

        if (!contactId) {
            if (window._crmLookups) {
                populateCrmLookupsDropdowns(window._crmLookups);
            }
            return;
        }

        fetch(`/nexFlow/admin/api/inbox.php?action=contact_companies&contact_id=${encodeURIComponent(contactId)}`, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                const companies = res.data.companies || [];
                const deals = res.data.deals || [];

                // Contact -> Company relationship:
                let cpHtml = '<option value="">-- None --</option>';
                if (companies.length > 0) {
                    companies.forEach(cp => {
                        const isSel = (preferredCompanyId && String(preferredCompanyId) === String(cp.id)) || (!preferredCompanyId && companies.length === 1);
                        cpHtml += `<option value="${cp.id}" ${isSel ? 'selected' : ''}>${escapeHtml(cp.name)}</option>`;
                    });
                }
                if (window._crmLookups && Array.isArray(window._crmLookups.companies)) {
                    const assocIds = new Set(companies.map(c => String(c.id)));
                    const otherCompanies = window._crmLookups.companies.filter(c => !assocIds.has(String(c.id)));
                    if (otherCompanies.length > 0) {
                        cpHtml += `<optgroup label="Other Companies">`;
                        otherCompanies.forEach(cp => {
                            const isSel = preferredCompanyId && String(preferredCompanyId) === String(cp.id);
                            cpHtml += `<option value="${cp.id}" ${isSel ? 'selected' : ''}>${escapeHtml(cp.name)}</option>`;
                        });
                        cpHtml += `</optgroup>`;
                    }
                }
                cpSel.innerHTML = cpHtml;

                // Contact -> Deals options
                if (dSel) {
                    let dHtml = '<option value="">-- None --</option>';
                    if (deals.length > 0) {
                        deals.forEach(d => {
                            const valStr = d.value ? ` ($${parseInt(d.value).toLocaleString()})` : '';
                            const isSel = (preferredDealId && String(preferredDealId) === String(d.id));
                            dHtml += `<option value="${d.id}" ${isSel ? 'selected' : ''}>${escapeHtml(d.name + valStr)}</option>`;
                        });
                    }
                    if (window._crmLookups && Array.isArray(window._crmLookups.deals)) {
                        const dealIds = new Set(deals.map(d => String(d.id)));
                        const otherDeals = window._crmLookups.deals.filter(d => !dealIds.has(String(d.id)));
                        if (otherDeals.length > 0) {
                            dHtml += `<optgroup label="Other Deals">`;
                            otherDeals.forEach(d => {
                                const valStr = d.value ? ` ($${parseInt(d.value).toLocaleString()})` : '';
                                const isSel = (preferredDealId && String(preferredDealId) === String(d.id));
                                dHtml += `<option value="${d.id}" ${isSel ? 'selected' : ''}>${escapeHtml(d.name + valStr)}</option>`;
                            });
                            dHtml += `</optgroup>`;
                        }
                    }
                    dSel.innerHTML = dHtml;
                }
            }
        })
        .catch(err => console.error("Failed to load contact associations:", err));
    }

    function submitComposeForm() {
        const channel   = document.getElementById("composeChannelSelect").value;
        const to        = document.getElementById("composeToInput").value.trim();
        const subject   = document.getElementById("composeSubjectInput") ? document.getElementById("composeSubjectInput").value.trim() : "";
        const body      = document.getElementById("composeBodyInput").value.trim();
        const contactId = document.getElementById("composeContactSelect") ? document.getElementById("composeContactSelect").value : "";
        const companyId = document.getElementById("composeCompanySelect") ? document.getElementById("composeCompanySelect").value : "";
        const dealId    = document.getElementById("composeDealSelect") ? document.getElementById("composeDealSelect").value : "";
        const draftId   = document.getElementById("composeDraftId") ? document.getElementById("composeDraftId").value : "";

        if (!to) {
            showInboxToast("Recipient is required.");
            document.getElementById("composeToInput").focus();
            return;
        }
        if (!body) {
            showInboxToast("Message content is required.");
            document.getElementById("composeBodyInput").focus();
            return;
        }

        const submitBtn = document.getElementById("composeSubmitBtn");
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Sending...";
        }

        const fd = new FormData();
        fd.append('action', 'compose');
        fd.append('channel', channel);
        fd.append('to', to);
        fd.append('subject', subject);
        fd.append('body', body);
        if (contactId) fd.append('contact_id', contactId);
        if (companyId) fd.append('company_id', companyId);
        if (dealId)    fd.append('deal_id', dealId);
        if (draftId)   fd.append('draft_id', draftId);

        fetch('/nexFlow/admin/api/inbox.php', {
            method: 'POST',
            body: fd
        })
        .then(res => res.json())
        .then(res => {
            if (submitBtn) {
                submitBtn.disabled = false;
                window.inboxApp.toggleComposeChannelFields(channel);
            }

            if (res.success && res.data && res.data.conversation_id) {
                const draftInput = document.getElementById("composeDraftId");
                if (draftInput) draftInput.value = "";

                window.inboxApp.closeComposeDrawer();

                // Trigger external link if applicable
                if (channel === "WhatsApp") {
                    const phone = to.replace(/[^0-9]/g, "");
                    showInboxToast("Conversation created. Opening WhatsApp...");
                    setTimeout(() => {
                        window.open(`https://wa.me/${phone}?text=${encodeURIComponent(body)}`, "_blank");
                    }, 300);
                } else if (channel === "Email") {
                    showInboxToast("Conversation created. Opening email app...");
                    setTimeout(() => {
                        window.location.href = `mailto:${to}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
                    }, 300);
                } else {
                    showInboxToast("Conversation created successfully.");
                }

                // Refresh list and select the newly composed conversation
                loadConversations(false);
                loadCounts();
                setTimeout(() => {
                    window.inboxApp.selectConversation(res.data.conversation_id);
                }, 400);
            } else {
                showInboxToast(res.message || "Failed to create conversation.");
            }
        })
        .catch(err => {
            if (submitBtn) {
                submitBtn.disabled = false;
                window.inboxApp.toggleComposeChannelFields(channel);
            }
            console.error("Compose error:", err);
            showInboxToast("Error submitting message.");
        });
    }

    function restoreKeyboardFocus() {
        if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
            lastFocusedElement.focus();
            lastFocusedElement = null;
        }
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    window.showInboxToast = function(msg) {
        const toast = document.createElement("div");
        toast.style.cssText = "position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); background-color: #101828; color: #ffffff; padding: 10px 18px; border-radius: 999px; font-size: 13px; font-weight: 500; box-shadow: 0 10px 15px rgba(0,0,0,0.2); z-index: 200; pointer-events: none; transition: opacity 0.3s ease;";
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = "0";
            setTimeout(() => toast.remove(), 300);
        }, 2500);
    };
})();
