/**
 * NexFlow CRM — Client Portal JavaScript Controller
 * Frontend-only demonstration controller.
 * Manages demo client state, navigation, drawers, modals, filters,
 * messages, tasks, calendar events, documents, profile, and toasts.
 */

(function () {
    const STORAGE_KEY = "NexFlow_client_data_v3";
    const SESSION_KEY = "NexFlow_client_session_v3";

    // Initial Mock State for Client Workspace (Marcus Thompson - Acme Corp)
    const defaultData = {
        user: {
            name: "Marcus Thompson",
            email: "marcus.thompson@acmecorp.com",
            role: "VP of Technology",
            company: "Acme Corp",
            avatar: "MT",
            phone: "+1 (555) 492-8100",
            timeZone: "America/New_York (EST)",
            notificationEmail: true,
            notificationInApp: true,
            notificationSMS: false
        },
        accountManager: {
            name: "Olivia Martin",
            email: "olivia.martin@NexFlow.io",
            role: "Senior Account Executive",
            avatar: "OM",
            phone: "+1 (555) 234-5678"
        },
        deals: [
            {
                id: "deal-101",
                name: "Acme Corp – Enterprise Expansion & CRM Suite",
                value: 48500,
                stage: "Proposal",
                probability: 60,
                closeDate: "2026-09-15",
                owner: "Olivia Martin",
                status: "In Progress",
                description: "Expansion across 150 additional sales seats with custom pipeline automation, enterprise SLA, and priority 24/7 dedicated support.",
                activity: [
                    { date: "2026-08-12", user: "Olivia Martin", text: "Sent revised Proposal v2.4 with updated seat licensing schedule." },
                    { date: "2026-08-08", user: "Marcus Thompson", text: "Requested SSO integration architecture diagram." },
                    { date: "2026-07-28", user: "Olivia Martin", text: "Completed technical requirements scoping session." }
                ],
                comments: [
                    { date: "2026-08-13 10:15 AM", user: "Marcus Thompson", text: "Our security compliance team is reviewing the SOC2 Type II report." }
                ]
            },
            {
                id: "deal-102",
                name: "Acme Corp - Enterprise Custom Integration & SSO",
                value: 18200,
                stage: "Qualified",
                probability: 30,
                closeDate: "2026-10-01",
                owner: "Olivia Martin",
                status: "Under Review",
                description: "Dedicated single-sign-on (SSO / SAML) authentication configuration and enterprise API middleware.",
                activity: [
                    { date: "2026-08-10", user: "Olivia Martin", text: "Shared technical API sandbox credentials and SAML endpoints." }
                ],
                comments: []
            },
            {
                id: "deal-103",
                name: "Acme Corp - Annual CRM Suite & SLA Renewal",
                value: 32000,
                stage: "Closed Won",
                probability: 100,
                closeDate: "2026-06-30",
                owner: "Olivia Martin",
                status: "Active",
                description: "Annual renewal for core CRM infrastructure and enterprise SLA support.",
                activity: [
                    { date: "2026-06-30", user: "Olivia Martin", text: "Deal successfully finalized and active for 2026-2027 fiscal term." }
                ],
                comments: []
            }
        ],
        tasks: [
            { id: "task-1", title: "Review & Sign Master Services Agreement Amendment", deal: "Enterprise Expansion & CRM Suite", priority: "High", dueDate: "2026-08-16", group: "Today", completed: false },
            { id: "task-2", title: "Submit Technical SSO & SAML Configuration Form", deal: "Enterprise Custom Integration", priority: "Urgent", dueDate: "2026-08-12", group: "Overdue", completed: false },
            { id: "task-3", title: "Confirm Team Training Session Attendees (25 Seats)", deal: "Enterprise Expansion & CRM Suite", priority: "Medium", dueDate: "2026-08-20", group: "Upcoming", completed: false },
            { id: "task-4", title: "Prepare technical SLA draft", deal: "Enterprise Custom Integration", priority: "High", dueDate: "2026-08-19", group: "Upcoming", completed: false },
            { id: "task-5", title: "Complete Initial Onboarding Questionnaire", deal: "Annual Support Renewal", priority: "Medium", dueDate: "2026-07-10", group: "Completed", completed: true }
        ],
        meetings: [
            { id: "evt-1", title: "Quarterly Executive Business Review", date: "2026-08-18", time: "10:00 AM - 11:00 AM", type: "Meeting", host: "Olivia Martin", location: "Zoom Video Room #481", status: "Confirmed" },
            { id: "evt-2", title: "Enterprise Custom Integration Technical Demo", date: "2026-08-22", time: "02:30 PM - 03:30 PM", type: "Demo", host: "Olivia Martin", location: "Google Meet", status: "Scheduled" },
            { id: "evt-3", title: "Security Compliance & SOC2 Architecture Review", date: "2026-08-26", time: "11:00 AM - 12:00 PM", type: "Review", host: "Olivia Martin", location: "Zoom Video Room #102", status: "Scheduled" }
        ],
        conversations: [
            {
                id: "conv-1",
                name: "Olivia Martin",
                role: "Senior Account Executive",
                avatar: "OM",
                unread: true,
                messages: [
                    { sender: "them", time: "Aug 12, 09:30 AM", text: "Good morning Marcus! Let me know if you had a chance to review the Enterprise Proposal v2.4." },
                    { sender: "you", time: "Aug 12, 10:15 AM", text: "Hi Olivia, our legal team is going through the SLA terms today. We should have feedback by tomorrow." },
                    { sender: "them", time: "Today, 10:42 AM", text: "I've uploaded the revised pricing schedule reflecting the 150-seat volume discount." }
                ]
            }
        ],
        documents: [],
        tickets: [
            { id: "tkt-1", category: "Technical", subject: "SSO Certificate Renewal", status: "Resolved", date: "2026-08-11", priority: "High" },
            { id: "tkt-2", category: "Billing", subject: "Q3 License True-up Confirmation", status: "In Progress", date: "2026-08-13", priority: "Medium" }
        ],
        notifications: [
            { id: "n1", title: "New Document Shared", desc: "Olivia Martin uploaded Enterprise Proposal v2.4", time: "2 hours ago", unread: true },
            { id: "n2", title: "Meeting Scheduled", desc: "Executive Business Review set for Aug 18", time: "1 day ago", unread: true },
            { id: "n3", title: "Task Due Soon", desc: "SSO Configuration Form is pending", time: "2 days ago", unread: false }
        ]
    };

    // Client Data Controller
    let clientData = loadState();

    function loadState() {
        let state;
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                state = JSON.parse(saved);
            }
        } catch (e) {
            console.warn("Could not load stored client data:", e);
        }
        if (!state) {
            state = JSON.parse(JSON.stringify(defaultData));
        }

        // Hydrate from authoritative window.INITIAL_PROFILE if provided
        if (window.INITIAL_PROFILE && window.INITIAL_PROFILE.personal) {
            const p = window.INITIAL_PROFILE.personal;
            const c = window.INITIAL_PROFILE.company || {};
            const l = window.INITIAL_PROFILE.account_lead || {};
            const n = window.INITIAL_PROFILE.notifications || {};
            state.user = {
                name: p.name || "",
                email: p.email || "",
                role: p.job_title || "",
                company: c.name || p.company_name || "",
                avatar: p.avatar_initials || "CP",
                phone: p.phone || "",
                timeZone: p.timezone || "America/New_York",
                notificationEmail: !!n.email_summaries,
                notificationInApp: !!n.in_portal,
                notificationSMS: false
            };
            if (l.has_lead && l.name) {
                state.accountManager = {
                    name: l.name,
                    email: l.email || "",
                    role: l.title || "Account Executive",
                    avatar: l.avatar_initials || "AE",
                    phone: l.phone || ""
                };
            }
        }

        return state;
    }

    function saveState() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(clientData));
        } catch (e) {
            console.warn("Could not save client data:", e);
        }
        updateBadgeCounts();
    }

    function updateBadgeCounts() {
        const unreadNotifs = clientData.notifications.filter(n => n.unread).length;
        const notifBadge = document.getElementById("clientNotifBadge");
        if (notifBadge) {
            notifBadge.classList.toggle("show", unreadNotifs > 0);
        }

        const openTasks = clientData.tasks.filter(t => !t.completed).length;
        const tasksChip = document.getElementById("navTasksCount");
        if (tasksChip) tasksChip.textContent = openTasks;

        const activeDeals = clientData.deals.length;
        const dealsChip = document.getElementById("navDealsCount");
        if (dealsChip) dealsChip.textContent = activeDeals;

        const unreadMsgs = clientData.conversations.filter(c => c.unread).length;
        const msgsChip = document.getElementById("navMessagesCount");
        if (msgsChip) msgsChip.textContent = unreadMsgs;
    }

    // Public Client Portal API
    window.clientPortal = {
        data: clientData,

        // Initialize Page Handlers
        init: function () {
            updateBadgeCounts();
            renderNotifications();
            this.setupGlobalClickListeners();
        },

        // Mobile Sidebar Toggle
        toggleMobileSidebar: function () {
            const sidebar = document.getElementById("clientSidebar");
            const backdrop = document.getElementById("clientSidebarBackdrop");
            if (sidebar && backdrop) {
                sidebar.classList.toggle("mobile-open");
                backdrop.classList.toggle("mobile-open");
            }
        },

        // Dropdown Toggle (User Menu & Notifications)
        toggleDropdown: function (menuId) {
            const menu = document.getElementById(menuId);
            if (!menu) return;
            const isShowing = menu.classList.contains("show");

            // Close other open menus
            document.querySelectorAll(".client-portal-menu.show").forEach(m => {
                if (m.id !== menuId) m.classList.remove("show");
            });

            menu.classList.toggle("show", !isShowing);
        },

        setupGlobalClickListeners: function () {
            document.addEventListener("click", function (e) {
                if (!e.target.closest(".client-portal-dropdown-wrapper")) {
                    document.querySelectorAll(".client-portal-menu.show").forEach(m => m.classList.remove("show"));
                }
                if (!e.target.closest(".client-portal-search-wrapper")) {
                    const searchResults = document.getElementById("clientSearchResults");
                    if (searchResults) searchResults.classList.remove("show");
                }
            });

            // Global Escape Key Listener for Modals & Drawers
            document.addEventListener("keydown", function (e) {
                if (e.key === "Escape") {
                    const modal = document.getElementById("clientModalOverlay");
                    if (modal && modal.classList.contains("show")) {
                        window.clientPortal.closeModal();
                        return;
                    }
                    const drawer = document.getElementById("clientDealDrawer");
                    if (drawer && drawer.classList.contains("show")) {
                        window.clientPortal.closeDealDrawer();
                        return;
                    }
                }
            });
        },

        // Logout action
        logout: function () {
            try {
                sessionStorage.removeItem(SESSION_KEY);
                localStorage.removeItem("NexFlow_client_logged_in");
            } catch (e) {}

            fetch("api/client-auth.php?action=logout", {
                method: "POST"
            }).finally(() => {
                window.clientPortal.showToast("Signed out of Client Portal.", "info");
                setTimeout(() => {
                    window.location.href = "login.php";
                }, 300);
            });
        },

        // Global Search
        handleGlobalSearch: function (query) {
            const resultsBox = document.getElementById("clientSearchResults");
            if (!resultsBox) return;
            const q = (query || "").trim().toLowerCase();
            if (!q) {
                resultsBox.classList.remove("show");
                resultsBox.innerHTML = "";
                return;
            }

            let matches = [];

            // Search Deals
            clientData.deals.forEach(d => {
                if (d.name.toLowerCase().includes(q) || d.stage.toLowerCase().includes(q)) {
                    matches.push({ type: "Deal", title: d.name, sub: `${d.stage} • $${d.value.toLocaleString()}`, action: `window.clientPortal.openDealDrawer('${d.id}')` });
                }
            });

            // Search Tasks
            clientData.tasks.forEach(t => {
                if (t.title.toLowerCase().includes(q) || t.deal.toLowerCase().includes(q)) {
                    matches.push({ type: "Task", title: t.title, sub: `Due ${t.dueDate} • ${t.priority}`, action: `window.location.href='client-tasks.php'` });
                }
            });

            // Search Documents
            clientData.documents.forEach(doc => {
                if (doc.name.toLowerCase().includes(q) || doc.type.toLowerCase().includes(q)) {
                    matches.push({ type: "Document", title: doc.name, sub: `${doc.type} • ${doc.size}`, action: `window.clientPortal.previewDocument('${doc.id}')` });
                }
            });

            if (matches.length === 0) {
                resultsBox.innerHTML = `<div style="padding:12px 16px;font-size:12.5px;color:#64748B;">No matching client items found.</div>`;
            } else {
                resultsBox.innerHTML = matches.slice(0, 6).map(m => `
                    <div style="padding:10px 14px;border-bottom:1px solid #F1F5F9;cursor:pointer;" onclick="${m.action};document.getElementById('clientSearchResults').classList.remove('show');">
                        <span class="client-portal-badge gray" style="font-size:10px;margin-bottom:2px;">${m.type}</span>
                        <div style="font-size:13px;font-weight:600;color:#0F172A;">${m.title}</div>
                        <div style="font-size:11.5px;color:#64748B;">${m.sub}</div>
                    </div>
                `).join("");
            }
            resultsBox.classList.add("show");
        },

        // Notification List Render & Actions
        markAllNotifsRead: function () {
            clientData.notifications.forEach(n => n.unread = false);
            saveState();
            renderNotifications();
            this.showToast("All notifications marked as read.", "success");
        },

        // Toast Messages Root
        showToast: function (msg, type = "info") {
            const container = document.getElementById("clientToastContainer");
            if (!container) return;
            const toast = document.createElement("div");
            toast.className = `client-portal-toast ${type}`;
            toast.innerHTML = `<span>${msg}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = "0";
                toast.style.transition = "opacity 0.25s ease";
                setTimeout(() => toast.remove(), 250);
            }, 3200);
        },

        // Modal Layer Management
        openModal: function (htmlContent) {
            const overlay = document.getElementById("clientModalOverlay");
            const card = document.getElementById("clientModalCard");
            if (overlay && card) {
                card.innerHTML = htmlContent;
                overlay.style.display = "flex";
                overlay.classList.add("show");
            }
        },

        closeModal: function () {
            const overlay = document.getElementById("clientModalOverlay");
            if (overlay) {
                overlay.classList.remove("show");
                overlay.style.display = "none";
            }
        },

        // Quick Action: Send Message Modal
        openQuickMessageModal: function (defaultSubject = "") {
            const html = `
                <div class="client-portal-modal-header">
                    <h3 class="client-portal-modal-title">Send Message to Account Team</h3>
                    <button type="button" class="client-portal-drawer-close" onclick="window.clientPortal.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form id="quickMessageForm" onsubmit="window.clientPortal.submitQuickMessage(event)">
                    <div class="client-portal-modal-body">
                        <div style="margin-bottom:14px;">
                            <label class="client-login-label">Recipient</label>
                            <input type="text" class="input-control" value="Olivia Martin (Senior Account Executive)" readonly style="background-color:#F8FAFC;">
                        </div>
                        <div style="margin-bottom:14px;">
                            <label class="client-login-label">Subject / Topic</label>
                            <input type="text" class="input-control" id="qmSubject" value="${defaultSubject}" placeholder="e.g. Question on Enterprise SLA amendment" required>
                        </div>
                        <div style="margin-bottom:6px;">
                            <label class="client-login-label">Message Content</label>
                            <textarea class="input-control" id="qmText" rows="4" style="height:90px;resize:none;padding: 8px 12px" placeholder="Write your message here..." required></textarea>
                        </div>
                    </div>
                    <div class="client-portal-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Send Message</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitQuickMessage: function (e) {
            e.preventDefault();
            const text = document.getElementById("qmText").value.trim();
            if (!text) return;
            const conv = clientData.conversations.find(c => c.id === "conv-1");
            if (conv) {
                conv.messages.push({
                    sender: "you",
                    time: "Just now",
                    text: text
                });
                saveState();
            }
            this.closeModal();
            this.showToast("Message sent to Olivia Martin.", "success");
        },

        // Quick Action: Schedule Request Modal
        openScheduleRequestModal: function () {
            const html = `
                <div class="client-portal-modal-header">
                    <h3 class="client-portal-modal-title">Request Meeting with Account Team</h3>
                    <button type="button" class="client-portal-drawer-close" onclick="window.clientPortal.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form id="scheduleRequestForm" onsubmit="window.clientPortal.submitScheduleRequest(event)">
                    <div class="client-portal-modal-body">
                        <div style="margin-bottom:14px;">
                            <label class="client-login-label">Meeting Topic *</label>
                            <input type="text" class="input-control" id="srTitle" placeholder="e.g. Q3 Roadmap Review & SLA Discussion" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                            <div>
                                <label class="client-login-label">Preferred Date *</label>
                                <input type="date" class="input-control" id="srDate" required value="2026-08-25">
                            </div>
                            <div>
                                <label class="client-login-label">Preferred Time *</label>
                                <select class="input-control" id="srTime">
                                    <option>10:00 AM - 11:00 AM EST</option>
                                    <option>02:00 PM - 03:00 PM EST</option>
                                    <option>04:00 PM - 05:00 PM EST</option>
                                </select>
                            </div>
                        </div>
                        <div style="margin-bottom:6px;">
                            <label class="client-login-label">Meeting Agenda / Notes</label>
                            <textarea class="input-control" id="srNotes" rows="3" style="height:70px;resize:none;padding: 8px 12px" placeholder="Brief agenda or questions for the team..."></textarea>
                        </div>
                    </div>
                    <div class="client-portal-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Request Meeting</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitScheduleRequest: function (e) {
            e.preventDefault();
            const title = document.getElementById("srTitle").value.trim();
            const date = document.getElementById("srDate").value;
            const time = document.getElementById("srTime").value;
            if (!title || !date) return;

            clientData.meetings.push({
                id: "evt-" + Date.now(),
                title: title,
                date: date,
                time: time,
                type: "Meeting",
                host: "Olivia Martin",
                location: "Zoom Video Meeting",
                status: "Pending Confirmation"
            });
            saveState();
            this.closeModal();
            this.showToast("Meeting request submitted to your account team.", "success");
            if (typeof renderClientCalendar === "function") renderClientCalendar();
        },

        // Quick Action: Upload Document Modal
        openUploadDocModal: function () {
            const html = `
                <div class="client-portal-modal-header">
                    <h3 class="client-portal-modal-title">Share Document with NexFlow Team</h3>
                    <button type="button" class="client-portal-drawer-close" onclick="window.clientPortal.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form id="uploadDocForm" onsubmit="window.clientPortal.submitUploadDoc(event)">
                    <div class="client-portal-modal-body">
                        <div style="margin-bottom:14px;">
                            <label class="client-login-label">Select File *</label>
                            <input type="file" class="input-control" id="docFileInput" required onchange="window.clientPortal.handleFileSelect(this)">
                        </div>
                        <div style="margin-bottom:14px;">
                            <label class="client-login-label">Document Category *</label>
                            <select class="input-control" id="docCategorySelect">
                                <option>Contract</option>
                                <option>Security Report</option>
                                <option>Proposal</option>
                                <option>Invoice</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div style="margin-bottom:6px;">
                            <label class="client-login-label">Related Deal</label>
                            <select class="input-control" id="docDealSelect">
                                ${clientData.deals.map(d => `<option>${d.name}</option>`).join("")}
                            </select>
                        </div>
                    </div>
                    <div class="client-portal-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Upload File</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        selectedFileMeta: null,
        handleFileSelect: function (input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                this.selectedFileMeta = {
                    name: file.name,
                    size: (file.size / 1024 / 1024).toFixed(1) + " MB"
                };
            }
        },

        submitUploadDoc: function (e) {
            e.preventDefault();
            const meta = this.selectedFileMeta || { name: "Uploaded_Document_" + Date.now() + ".pdf", size: "1.2 MB" };
            const type = document.getElementById("docCategorySelect").value;
            const deal = document.getElementById("docDealSelect").value;

            clientData.documents.unshift({
                id: "doc-" + Date.now(),
                name: meta.name,
                type: type,
                size: meta.size,
                deal: deal,
                uploadedBy: clientData.user.name,
                date: new Date().toISOString().split("T")[0]
            });
            saveState();
            this.closeModal();
            this.showToast(`Document "${meta.name}" uploaded successfully.`, "success");
            if (typeof renderClientDocuments === "function") renderClientDocuments();
        },

        // Document Preview Modal (Enhanced for PDF and DOCX)
        previewDocument: function (docId) {
            const doc = clientData.documents.find(d => d.id === docId);
            if (!doc) return;
            const isDocx = doc.name.toLowerCase().endsWith('.docx');

            let previewBody = '';
            if (isDocx) {
                previewBody = `
                    <div style="background-color:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:36px 20px;text-align:center;margin-bottom:16px;">
                        <div style="width:52px;height:52px;border-radius:10px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;margin:0 auto 14px auto;">
                            <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <h4 style="font-size:15px;font-weight:700;color:#0F172A;margin:0 0 6px 0;">${doc.name}</h4>
                        <p style="font-size:12.5px;color:#64748B;margin:0 0 14px 0;">Category: ${doc.type} • File Size: ${doc.size} • Uploaded by: ${doc.uploadedBy}</p>
                        <div style="display:inline-block;padding:8px 14px;background:#FFFBEB;border:1px solid #FDE68A;border-radius:6px;font-size:12px;color:#B45309;font-weight:500;">
                            ⚠️ Preview unavailable in browser for .docx format. Please download the document to view full formatting.
                        </div>
                    </div>
                `;
            } else {
                previewBody = `
                    <div style="background-color:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:20px;margin-bottom:16px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:20px;border-bottom:1px solid #E2E8F0;padding-bottom:12px;margin-bottom:14px;">
                            <div style="display:flex;align-items:center;gap:12px;min-width:0;flex:1;">
                                <div style="width:36px;height:36px;border-radius:6px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </div>
                                <div style="min-width:0;flex:1;">
                                    <div style="font-size:14px;font-weight:700;color:#0F172A;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${doc.name}">${doc.name}</div>
                                    <div style="font-size:11.5px;color:#64748B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Category: ${doc.type} • ${doc.size} • Verified PDF</div>
                                </div>
                            </div>
                            <span class="client-portal-badge green" style="flex-shrink:0;margin-left:auto;">Verified Archive</span>
                        </div>
                        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:20px;min-height:150px;box-shadow:0 1px 3px rgba(0,0,0,0.04);font-size:12.5px;color:#334155;line-height:1.6;">
                            <div style="display:flex;justify-content:space-between;border-bottom:1px solid #F1F5F9;padding-bottom:8px;margin-bottom:12px;font-weight:600;color:#0F172A;">
                                <span>NexFlow TECHNOLOGIES — CLIENT PORTAL VERIFIED ARCHIVE</span>
                                <span>PAGE 1 OF 4</span>
                            </div>
                            <p style="margin:0 0 6px 0;"><strong>Document:</strong> ${doc.name}</p>
                            <p style="margin:0 0 6px 0;"><strong>Associated Account:</strong> ${clientData.user.company} (${clientData.user.name})</p>
                            <p style="margin:0 0 6px 0;"><strong>Uploaded By:</strong> ${doc.uploadedBy} on ${doc.date}</p>
                            <p style="color:#64748B;font-size:12px;margin-top:10px;">This document preview demonstrates the verified client archive record. Download below for offline review or signature records.</p>
                        </div>
                    </div>
                `;
            }

            const html = `
                <div class="client-portal-modal-header">
                    <h3 class="client-portal-modal-title">Document Preview: ${doc.name}</h3>
                    <button type="button" class="client-portal-drawer-close" onclick="window.clientPortal.closeModal()" aria-label="Close preview">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="client-portal-modal-body">
                    ${previewBody}
                </div>
                <div class="client-portal-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.closeModal()">Close</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.downloadDemoDoc('${doc.name}')">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Download File
                    </button>
                </div>
            `;
            this.openModal(html);
        },

        downloadDemoDoc: function (fileName) {
            this.showToast(`Downloading demo file: ${fileName}`, "info");
        },

        // Deal Details Drawer
        activeDealId: null,
        activeDealTab: "overview",
        lastFocusedDealBtn: null,

        openDealDrawer: function (dealId, triggerBtn) {
            this.lastFocusedDealBtn = triggerBtn || document.activeElement;
            const deal = clientData.deals.find(d => d.id === dealId) || clientData.deals[0];
            if (!deal) return;
            this.activeDealId = deal.id;
            this.activeDealTab = "overview";

            const drawer = document.getElementById("clientDealDrawer");
            const title = document.getElementById("clientDealDrawerTitle");
            const badge = document.getElementById("cddStageBadge");
            const sendMsgBtn = document.getElementById("btnDrawerSendMessage");

            if (title) title.textContent = deal.name;
            if (badge) {
                badge.textContent = deal.stage;
                badge.className = "client-portal-drawer-badge " + (
                    deal.stage === 'Closed Won' ? 'stage-won' :
                    deal.stage === 'Proposal' ? 'stage-proposal' :
                    deal.stage === 'Qualified' ? 'stage-qualified' : 'stage-discovery'
                );
            }

            if (sendMsgBtn) {
                const ownerSlug = (deal.owner || "olivia-martin").toLowerCase().replace(/\s+/g, '-');
                sendMsgBtn.setAttribute("href", `client-messages.php?contact=${ownerSlug}`);
            }

            this.switchDealDrawerTab("overview");
            if (drawer) {
                drawer.style.display = "block";
                drawer.classList.add("show");
                document.body.style.overflow = "hidden";
                const firstTab = drawer.querySelector(".client-portal-drawer-tab");
                if (firstTab) firstTab.focus();
            }
        },

        closeDealDrawer: function () {
            const drawer = document.getElementById("clientDealDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.style.display = "none";
                document.body.style.overflow = "";
            }
            if (this.lastFocusedDealBtn && typeof this.lastFocusedDealBtn.focus === "function") {
                this.lastFocusedDealBtn.focus();
            }
        },

        switchDealDrawerTab: function (tabName) {
            this.activeDealTab = tabName;
            document.querySelectorAll(".client-portal-drawer-tab").forEach(tab => {
                const isActive = tab.getAttribute("data-tab") === tabName;
                tab.classList.toggle("active", isActive);
                tab.setAttribute("aria-selected", isActive ? "true" : "false");
            });

            const deal = clientData.deals.find(d => d.id === this.activeDealId) || clientData.deals[0];
            const body = document.getElementById("clientDealDrawerBody");
            if (!body || !deal) return;

            if (tabName === "overview") {
                // Stage Progress Logic: Completed = green, Current = blue, Future = gray
                const st = (deal.stage || '').toLowerCase();
                let s0 = 'future', s1 = 'future', s2 = 'future', s3 = 'future';
                if (st.includes('discovery')) {
                    s0 = 'current';
                } else if (st.includes('qualified')) {
                    s0 = 'done'; s1 = 'current';
                } else if (st.includes('proposal')) {
                    s0 = 'done'; s1 = 'done'; s2 = 'current';
                } else if (st.includes('negotiat')) {
                    s0 = 'done'; s1 = 'done'; s2 = 'done'; s3 = 'current';
                } else if (st.includes('won') || st.includes('active') || st.includes('closed')) {
                    s0 = 'done'; s1 = 'done'; s2 = 'done'; s3 = 'done';
                } else {
                    s0 = 'done'; s1 = 'current';
                }

                body.innerHTML = `
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">ESTIMATED VALUE</div>
                            <div style="font-size:18px;font-weight:700;color:#0F172A;margin-top:2px;">$${deal.value.toLocaleString()}</div>
                        </div>
                        <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid #E2E8F0;">
                            <div style="font-size:11px;color:#64748B;font-weight:600;">EXPECTED CLOSE</div>
                            <div style="font-size:14px;font-weight:700;color:#0F172A;margin-top:4px;">${deal.closeDate}</div>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:8px;">Stage Progress</h4>
                        <div class="client-portal-deal-progress" style="margin:0;">
                            <div class="client-portal-pipeline-track">
                                <div class="client-portal-pipeline-step ${s0}" title="Discovery: ${s0}"></div>
                                <div class="client-portal-pipeline-step ${s1}" title="Qualified: ${s1}"></div>
                                <div class="client-portal-pipeline-step ${s2}" title="Proposal: ${s2}"></div>
                                <div class="client-portal-pipeline-step ${s3}" title="Final SLA: ${s3}"></div>
                            </div>
                            <div class="client-portal-pipeline-labels">
                                <span style="${s0==='done'?'color:#047857;font-weight:600;':s0==='current'?'color:#2563EB;font-weight:700;':''}">Discovery</span>
                                <span style="${s1==='done'?'color:#047857;font-weight:600;':s1==='current'?'color:#2563EB;font-weight:700;':''}">Qualified</span>
                                <span style="${s2==='done'?'color:#047857;font-weight:600;':s2==='current'?'color:#2563EB;font-weight:700;':''}">Proposal</span>
                                <span style="${s3==='done'?'color:#047857;font-weight:600;':s3==='current'?'color:#2563EB;font-weight:700;':''}">Final SLA</span>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:6px;">Scope Description</h4>
                        <p style="font-size:13px;color:#475569;line-height:1.55;margin:0;">${deal.description}</p>
                    </div>

                    <div>
                        <h4 style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:6px;">NexFlow Account Lead</h4>
                        <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;">
                            <div class="client-portal-avatar" style="background-color:#7C3AED;">OM</div>
                            <div style="flex:1;">
                                <div style="font-size:13.5px;font-weight:600;color:#0F172A;">${deal.owner}</div>
                                <div style="font-size:11.5px;color:#64748B;">Senior Account Executive • NexFlow</div>
                            </div>
                            <a href="client-messages.php?contact=olivia-martin" class="btn btn-secondary btn-xs">Direct Message</a>
                        </div>
                    </div>
                `;
            } else if (tabName === "activity") {
                body.innerHTML = `
                    <div style="display:flex;flex-direction:column;gap:14px;">
                        ${(deal.activity || []).map(a => `
                            <div style="display:flex;gap:12px;font-size:13px;align-items:flex-start;">
                                <div style="width:8px;height:8px;border-radius:50%;background:#2563EB;margin-top:6px;flex-shrink:0;"></div>
                                <div style="flex:1;">
                                    <div style="font-weight:600;color:#0F172A;">${a.user} <span style="font-size:11px;font-weight:400;color:#64748B;">• ${a.date}</span></div>
                                    <div style="color:#475569;margin-top:3px;line-height:1.45;">${a.text}</div>
                                </div>
                            </div>
                        `).join("")}
                    </div>
                `;
            } else if (tabName === "tasks") {
                const dealKeywords = (deal.name + " " + deal.description).toLowerCase();
                const tasks = clientData.tasks.filter(t => {
                    return dealKeywords.includes(t.deal.toLowerCase()) || t.deal.toLowerCase().includes("expansion") || t.deal.toLowerCase().includes("security") || t.deal.toLowerCase().includes("annual");
                });

                if (tasks.length === 0) {
                    body.innerHTML = '<p style="font-size:13px;color:#64748B;text-align:center;padding:24px 0;">No active tasks for this contract milestone.</p>';
                    return;
                }

                body.innerHTML = `
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        ${tasks.map(t => `
                            <div class="client-portal-task-item" style="border:1px solid #E2E8F0;border-radius:6px;padding:10px 14px;background:#FFFFFF;">
                                <div class="client-portal-task-left">
                                    <input type="checkbox" class="client-portal-task-checkbox" ${t.completed ? 'checked' : ''} onchange="window.clientPortal.toggleDealTaskCompletion('${t.id}')">
                                    <div>
                                        <div class="client-portal-task-title ${t.completed ? 'completed' : ''}" style="${t.completed ? 'text-decoration:line-through;color:#94A3B8;' : ''}">${t.title}</div>
                                        <div class="client-portal-task-meta">Due ${t.dueDate} • Priority: ${t.priority}</div>
                                    </div>
                                </div>
                            </div>
                        `).join("")}
                    </div>
                `;
            } else if (tabName === "documents") {
                const docs = clientData.documents;
                body.innerHTML = `
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        ${docs.map(doc => `
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="client-portal-doc-icon" style="width:34px;height:34px;border-radius:6px;">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                                    </div>
                                    <div>
                                        <div style="font-size:13px;font-weight:600;color:#0F172A;">${doc.name}</div>
                                        <div style="font-size:11px;color:#64748B;">${doc.type} • ${doc.size}</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientPortal.previewDocument('${doc.id}')">Preview</button>
                            </div>
                        `).join("")}
                    </div>
                `;
            } else if (tabName === "comments") {
                body.innerHTML = `
                    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:18px;">
                        ${(deal.comments && deal.comments.length > 0) ? deal.comments.map(c => `
                            <div style="background:#F8FAFC;border:1px solid #E2E8F0;padding:12px 14px;border-radius:8px;">
                                <div style="display:flex;justify-content:space-between;font-size:11px;color:#64748B;margin-bottom:4px;">
                                    <strong style="color:#0F172A;font-size:12px;">${c.user}</strong>
                                    <span>${c.date}</span>
                                </div>
                                <div style="font-size:13px;color:#334155;line-height:1.45;">${c.text}</div>
                            </div>
                        `).join("") : '<p style="font-size:12.5px;color:#64748B;margin:0 0 10px 0;">No notes or requests recorded yet for this deal.</p>'}
                    </div>
                    <div style="border-top:1px solid #E2E8F0;padding-top:14px;">
                        <label class="client-login-label" style="margin-bottom:10px;">Add Note or Client Request *</label>
                        <textarea class="input-control" id="dealCommentInput" rows="3" style="height:75px;resize:none;margin-bottom:10px;padding: 8px 12px" placeholder="Add question or note for your account executive..."></textarea>
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.submitDealComment()">Submit Note</button>
                    </div>
                `;
            }
        },

        submitDealComment: function () {
            const input = document.getElementById("dealCommentInput");
            if (!input) return;
            const text = input.value.trim();
            if (!text) {
                this.showToast("Please enter a note or request before submitting.", "warning");
                input.focus();
                return;
            }
            const deal = clientData.deals.find(d => d.id === this.activeDealId);
            if (deal) {
                if (!deal.comments) deal.comments = [];
                const now = new Date();
                const dateStr = now.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }) + " " + now.toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit" });
                deal.comments.push({
                    date: dateStr,
                    user: clientData.user.name || "Marcus Thompson",
                    text: text
                });
                saveState();
                this.switchDealDrawerTab("comments");
                this.showToast("Note / request submitted to your account team.", "success");
            }
        },

        // Task Completion Toggle in Drawer and Task View
        toggleDealTaskCompletion: function (taskId) {
            const task = clientData.tasks.find(t => t.id === taskId);
            if (task) {
                task.completed = !task.completed;
                task.group = task.completed ? "Completed" : "Today";
                saveState();
                this.switchDealDrawerTab("tasks");
                this.showToast(`Task ${task.completed ? 'marked as completed' : 'reopened'}.`, "success");
                if (typeof renderClientTasks === "function") renderClientTasks();
                if (typeof renderDashboardTasks === "function") renderDashboardTasks();
            }
        },

        toggleTaskCompletion: function (taskId) {
            this.toggleDealTaskCompletion(taskId);
        }
    };

    function renderNotifications() {
        const list = document.getElementById("clientNotifList");
        if (!list) return;
        list.innerHTML = clientData.notifications.map(n => `
            <div class="client-portal-notif-item ${n.unread ? 'unread' : ''}">
                <div style="width:8px;height:8px;border-radius:50%;background:${n.unread ? '#2563EB' : '#94A3B8'};margin-top:4px;flex-shrink:0;"></div>
                <div>
                    <div style="font-size:12.5px;font-weight:600;color:#0F172A;">${n.title}</div>
                    <div style="font-size:11.5px;color:#64748B;">${n.desc}</div>
                    <div style="font-size:10.5px;color:#94A3B8;margin-top:2px;">${n.time}</div>
                </div>
            </div>
        `).join("");
    }

    // Auto-init on DOMContentLoaded
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => window.clientPortal.init());
    } else {
        window.clientPortal.init();
    }
})();

