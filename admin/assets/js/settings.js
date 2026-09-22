/**
 * NexFlow CRM - Settings JavaScript Controller
 * Idempotent initialization, navigation section switching, settings search,
 * unsaved-change tracking, localStorage persistence, profile avatar preview,
 * drag-and-drop pipeline editor, custom fields drawer, CSV preview/export,
 * JSON backup export, and local demo data clearing.
 */

(function () {
    const STORAGE_PREFIX = "NexFlow_settings_";

    // Default Mock Configuration State
    const defaultState = {
        profile: {
            firstName: "Olivia",
            lastName: "Martin",
            displayName: "Olivia Martin",
            email: "olivia.martin@NexFlow.io",
            phone: "+1 (555) 234-5678",
            jobTitle: "Sales Manager",
            department: "Sales",
            location: "San Francisco, CA",
            timeZone: "America/Los_Angeles",
            language: "en-US",
            bio: "Leading SMB & Enterprise sales teams at NexFlow CRM.",
            linkedin: "https://linkedin.com/in/oliviamartin-NexFlow",
            avatar: null
        },
        preferences: {
            language: "en-US",
            timeZone: "America/Los_Angeles",
            dateFormat: "MMM DD, YYYY",
            timeFormat: "12h",
            weekStartsOn: "Sunday",
            currency: "USD ($)",
            numberFormat: "1,234.56",
            defaultLanding: "dashboard",
            pageSize: "25",
            density: "comfortable"
        },
        notifications: {
            leadAssignedInApp: true, leadAssignedEmail: true, leadAssignedDesktop: false,
            leadStatusInApp: true, leadStatusEmail: false, leadStatusDesktop: false,
            leadFollowupInApp: true, leadFollowupEmail: true, leadFollowupDesktop: true,
            dealAssignedInApp: true, dealAssignedEmail: true, dealAssignedDesktop: false,
            dealStageInApp: true, dealStageEmail: false, dealStageDesktop: false,
            taskDueInApp: true, taskDueEmail: true, taskDueDesktop: true,
            callMissedInApp: true, callMissedEmail: false, callMissedDesktop: true,
            inboxAssignedInApp: true, inboxAssignedEmail: true, inboxAssignedDesktop: false
        },
        company: {
            name: "NexFlow CRM",
            website: "https://NexFlowcrm.com",
            industry: "Software & Technology",
            size: "50-200 employees",
            phone: "+1 (800) 555-0199",
            email: "contact@NexFlowcrm.com",
            country: "United States",
            address: "500 Howard Street, Suite 400",
            city: "San Francisco",
            state: "CA",
            zip: "94105",
            currency: "USD ($)",
            fiscalStart: "January",
            hours: "09:00 - 17:00 PST",
            logo: null
        },
        teamDefaults: {
            defaultOwner: "Olivia Martin",
            defaultDealOwner: "Olivia Martin",
            defaultTaskAssignee: "Olivia Martin",
            defaultTeam: "SMB Sales",
            defaultStatus: "Available",
            workingDays: ["Mon", "Tue", "Wed", "Thu", "Fri"],
            workingHours: "09:00 - 17:00",
            quotaPeriod: "Monthly",
            defaultQuota: "$50,000"
        },
        pipeline: [
            { id: "p1", name: "Prospect", color: "#64748B", prob: 10, wonLost: "normal" },
            { id: "p2", name: "Qualified", color: "#2563EB", prob: 30, wonLost: "normal" },
            { id: "p3", name: "Proposal", color: "#F59E0B", prob: 60, wonLost: "normal" },
            { id: "p4", name: "Negotiation", color: "#8B5CF6", prob: 80, wonLost: "normal" },
            { id: "p5", name: "Closed Won", color: "#10B981", prob: 100, wonLost: "won" },
            { id: "p6", name: "Closed Lost", color: "#EF4444", prob: 0, wonLost: "lost" }
        ],
        leads: {
            defaultStatus: "New",
            defaultSource: "Website",
            defaultOwner: "Olivia Martin",
            scoreEnabled: true,
            scoreThreshold: 70,
            duplicateDetection: true,
            inactivityDays: 14,
            autoFollowup: true
        },
        customFields: [
            { id: "cf1", name: "Budget Range", object: "Leads", type: "Dropdown", required: false, visible: true, order: 1 },
            { id: "cf2", name: "Contract Start Date", object: "Deals", type: "Date", required: true, visible: true, order: 2 },
            { id: "cf3", name: "Tax Registration Number", object: "Companies", type: "Text", required: false, visible: true, order: 3 },
            { id: "cf4", name: "Decision Maker Score", object: "Contacts", type: "Number", required: false, visible: true, order: 4 }
        ],
        tasks: {
            defaultStatus: "Pending",
            defaultPriority: "Medium",
            defaultReminder: "15 minutes before",
            showCompleted: true,
            defaultView: "list",
            defaultAssignee: "Olivia Martin"
        },
        email: {
            senderName: "Olivia Martin",
            replyTo: "olivia.martin@NexFlow.io",
            signature: "Best regards,\nOlivia Martin\nSales Manager | NexFlow CRM",
            includeCompanySig: true,
            trackOpens: true,
            trackClicks: true
        },
        calling: {
            countryCode: "+1",
            defaultAvailability: "Available",
            autoCallNotes: true,
            showFloatingWidget: true,
            timerDisplay: true,
            recentCallsLimit: 20,
            simulatedIncoming: true
        },
        inbox: {
            defaultFolder: "inbox",
            sortOrder: "newest",
            markReadOnOpen: true,
            showContactContextOnClick: true,
            defaultReplyChannel: "email",
            showArchived: false,
            density: "comfortable"
        }
    };

    let state = JSON.parse(JSON.stringify(defaultState));
    let isDirty = false;
    let activeCustomFieldsTab = "Leads";
    let pendingAvatarFile = null;
    let removeAvatarRequested = false;

    function fetchUserProfile() {
        fetch('/nexFlow/admin/api/profile.php', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const p = data.data;
                state.profile = {
                    firstName: p.first_name || '',
                    lastName: p.last_name || '',
                    displayName: p.display_name || p.name || '',
                    email: p.email || '',
                    phone: p.phone || '',
                    jobTitle: p.job_title || '',
                    department: p.department || '',
                    location: p.location || '',
                    timezone: p.timezone || 'America/Los_Angeles',
                    language: p.language || 'en-US',
                    bio: p.short_bio || '',
                    linkedin: p.linkedin_url || '',
                    avatar: p.photo_url || null,
                    initials: p.initials || 'OM'
                };
                populateProfileFields();
                updateUserHeaderElements(p);
            }
        })
        .catch(err => {
            console.error("Failed to load profile from backend:", err);
        });
    }

    function populateProfileFields() {
        setVal("profileFirstName", state.profile.firstName);
        setVal("profileLastName", state.profile.lastName);
        setVal("profileDisplayName", state.profile.displayName);
        setVal("profileEmail", state.profile.email);
        setVal("profilePhone", state.profile.phone);
        setVal("profileJobTitle", state.profile.jobTitle);

        const deptEl = document.getElementById("profileDepartment");
        if (deptEl && state.profile.department) {
            deptEl.value = state.profile.department;
        }

        setVal("profileLocation", state.profile.location);

        const tzEl = document.getElementById("profileTimeZone");
        if (tzEl && state.profile.timezone) {
            tzEl.value = state.profile.timezone;
        }

        const langEl = document.getElementById("profileLanguage");
        if (langEl && state.profile.language) {
            langEl.value = state.profile.language;
        }

        setVal("profileBio", state.profile.bio);
        setVal("profileLinkedin", state.profile.linkedin);

        const previewContainer = document.getElementById("avatarPreviewContainer");
        if (previewContainer) {
            if (state.profile.avatar) {
                previewContainer.innerHTML = `<img src="${state.profile.avatar}" alt="Avatar Preview" class="settings-avatar-preview" style="width:64px;height:64px;border-radius:50%;object-fit:cover;">`;
            } else {
                const initText = state.profile.initials || 'OM';
                previewContainer.innerHTML = `<div class="settings-avatar-preview">${initText}</div>`;
            }
        }
    }

    function updateUserHeaderElements(user) {
        const displayName = user.display_name || user.name || 'Administrator';
        const email = user.email || '';
        const role = user.role ? (user.role === 'admin' ? 'Administrator' : user.role) : 'Administrator';
        const initials = user.initials || 'OM';
        const photoUrl = user.photo_url || null;

        const userMenuBtn = document.getElementById("userMenuBtn");
        if (userMenuBtn) {
            if (photoUrl) {
                userMenuBtn.innerHTML = `
                    <img src="${photoUrl}" alt="${displayName}" class="avatar avatar-sm topbar-user-photo" style="object-fit: cover; border-radius: 50%; width: 32px; height: 32px;">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color: var(--text-muted);">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                `;
            } else {
                userMenuBtn.innerHTML = `
                    <div class="avatar avatar-sm topbar-user-avatar" style="background-color: #7C3AED;">${initials}</div>
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color: var(--text-muted);">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                `;
            }
        }

        const userMenu = document.getElementById("userMenu");
        if (userMenu) {
            const nameP = userMenu.querySelector("p[style*='font-weight: 600']");
            if (nameP) nameP.textContent = displayName;
            const emailP = userMenu.querySelector("p[style*='font-size: 11px']");
            if (emailP) emailP.textContent = email || role;
        }

        const sidebarProfile = document.querySelector(".sidebar-profile");
        if (sidebarProfile) {
            const nameEl = sidebarProfile.querySelector(".sidebar-profile-name");
            if (nameEl) nameEl.textContent = displayName;
            const roleEl = sidebarProfile.querySelector(".sidebar-profile-role");
            if (roleEl) roleEl.textContent = role;

            const oldImg = sidebarProfile.querySelector("img");
            const oldAvatar = sidebarProfile.querySelector(".avatar");

            if (photoUrl) {
                if (oldImg) {
                    oldImg.src = photoUrl;
                    oldImg.alt = displayName;
                } else if (oldAvatar) {
                    const img = document.createElement("img");
                    img.src = photoUrl;
                    img.alt = displayName;
                    img.className = "avatar avatar-sm sidebar-user-photo";
                    img.style.cssText = "object-fit: cover; border-radius: 50%; width: 32px; height: 32px;";
                    oldAvatar.parentNode.replaceChild(img, oldAvatar);
                }
            } else {
                if (oldImg) {
                    const div = document.createElement("div");
                    div.className = "avatar avatar-sm sidebar-user-avatar";
                    div.style.backgroundColor = "#7C3AED";
                    div.textContent = initials;
                    oldImg.parentNode.replaceChild(div, oldImg);
                } else if (oldAvatar) {
                    oldAvatar.textContent = initials;
                }
            }
        }
    }

    function fetchUserPreferences() {
        fetch('/nexFlow/admin/api/preferences.php', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const p = data.data;
                state.preferences = {
                    language: p.language || 'en-US',
                    timeZone: p.timezone || 'America/Los_Angeles',
                    dateFormat: p.date_format || 'MMM DD, YYYY',
                    timeFormat: p.time_format || '12h',
                    weekStartsOn: p.week_starts_on || 'Sunday',
                    currency: p.currency || 'USD ($)',
                    numberFormat: p.number_format || '1,234.56',
                    defaultLanding: p.landing_page || 'dashboard',
                    pageSize: String(p.table_rows || 25),
                    density: p.interface_density || 'comfortable'
                };
                populatePreferencesFields();
            }
        })
        .catch(err => {
            console.error("Failed to load preferences from backend:", err);
        });
    }

    function populatePreferencesFields() {
        setVal("prefLanguage", state.preferences.language);
        setVal("prefTimeZone", state.preferences.timeZone);
        setVal("prefDateFormat", state.preferences.dateFormat);
        setVal("prefTimeFormat", state.preferences.timeFormat);
        setVal("prefWeekStartsOn", state.preferences.weekStartsOn);
        setVal("prefCurrency", state.preferences.currency);
        setVal("prefNumberFormat", state.preferences.numberFormat);
        setVal("prefDefaultLanding", state.preferences.defaultLanding);
        setVal("prefPageSize", state.preferences.pageSize);
        setVal("prefDensity", state.preferences.density);
    }

    function fetchUserNotifications() {
        fetch('/nexFlow/admin/api/notifications.php', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const n = data.data;
                state.notifications = {
                    leadAssignedInApp: !!n.new_lead_assigned,
                    leadStatusInApp: !!n.lead_status_changed,
                    leadFollowupInApp: !!n.follow_up_due,
                    dealAssignedInApp: !!n.deal_assigned,
                    taskDueInApp: !!n.task_due_soon,
                    callMissedInApp: !!n.missed_simulated_call
                };
                populateNotificationFields();
            }
        })
        .catch(err => {
            console.error("Failed to load notifications from backend:", err);
        });
    }

    function populateNotificationFields() {
        setChecked("notif_leadAssignedInApp", state.notifications.leadAssignedInApp);
        setChecked("notif_leadStatusInApp", state.notifications.leadStatusInApp);
        setChecked("notif_leadFollowupInApp", state.notifications.leadFollowupInApp);
        setChecked("notif_dealAssignedInApp", state.notifications.dealAssignedInApp);
        setChecked("notif_taskDueInApp", state.notifications.taskDueInApp);
        setChecked("notif_callMissedInApp", state.notifications.callMissedInApp);
    }

    function setupNotificationToggleListeners() {
        const notifMap = {
            "notif_leadAssignedInApp": "new_lead_assigned",
            "notif_leadStatusInApp": "lead_status_changed",
            "notif_leadFollowupInApp": "follow_up_due",
            "notif_dealAssignedInApp": "deal_assigned",
            "notif_taskDueInApp": "task_due_soon",
            "notif_callMissedInApp": "missed_simulated_call"
        };

        Object.keys(notifMap).forEach(elemId => {
            const el = document.getElementById(elemId);
            if (el) {
                el.addEventListener("change", function () {
                    const isChecked = this.checked;
                    state.notifications[elemId.replace("notif_", "")] = isChecked;

                    const params = new URLSearchParams();
                    params.append("new_lead_assigned", getChecked("notif_leadAssignedInApp") ? "1" : "0");
                    params.append("lead_status_changed", getChecked("notif_leadStatusInApp") ? "1" : "0");
                    params.append("follow_up_due", getChecked("notif_leadFollowupInApp") ? "1" : "0");
                    params.append("deal_assigned", getChecked("notif_dealAssignedInApp") ? "1" : "0");
                    params.append("task_due_soon", getChecked("notif_taskDueInApp") ? "1" : "0");
                    params.append("missed_simulated_call", getChecked("notif_callMissedInApp") ? "1" : "0");

                    fetch("/nexFlow/admin/api/notifications.php", {
                        method: "POST",
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: params.toString()
                    })
                    .then(res => res.json())
                    .then(resData => {
                        if (resData.success) {
                            showToast("Notification preference updated.", "info");
                        } else {
                            showToast(resData.message || "Failed to update notification setting.", "warning");
                        }
                    })
                    .catch(err => {
                        console.error("Failed to save notification preference:", err);
                        showToast("Error updating notification setting.", "warning");
                    });
                });
            }
        });
    }

    function fetchCompanyProfile() {
        fetch('/nexFlow/admin/api/settings.php?action=company_profile', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const c = data.data;
                state.company = {
                    name: c.name || '',
                    website: c.website || '',
                    industry: c.industry || '',
                    size: c.company_size || '50-200 employees',
                    phone: c.phone || '',
                    email: c.business_email || '',
                    country: c.country || '',
                    city: c.city || '',
                    state: c.state_region || '',
                    zip: c.postal_code || '',
                    fiscalStart: c.fiscal_year_start || 'January',
                    hours: c.business_hours || '09:00 - 17:00 PST'
                };
                populateCompanyFields();
            }
        })
        .catch(err => {
            console.error("Failed to load company profile from backend:", err);
        });
    }

    function populateCompanyFields() {
        setVal("compName", state.company.name);
        setVal("compWebsite", state.company.website);
        setVal("compIndustry", state.company.industry);
        setVal("compPhone", state.company.phone);
        setVal("compEmail", state.company.email);
        setVal("compCountry", state.company.country);
        setVal("compCity", state.company.city);
        setVal("compState", state.company.state);
        setVal("compZip", state.company.zip);
        setVal("compFiscalStart", state.company.fiscalStart);
        setVal("compHours", state.company.hours);

        const sizeSelect = document.getElementById("compSize");
        if (sizeSelect && state.company.size) {
            const val = String(state.company.size).trim();
            const cleanVal = val.replace(/ employees$/i, '').trim();
            let matchOpt = Array.from(sizeSelect.options).find(o => o.value === val || o.value === cleanVal || o.text === val || o.text.startsWith(cleanVal));
            if (matchOpt) {
                sizeSelect.value = matchOpt.value;
            } else if (cleanVal) {
                const newOpt = new Option(cleanVal + ' employees', cleanVal);
                sizeSelect.add(newOpt);
                sizeSelect.value = cleanVal;
            }
        }
    }

    function fetchTeamDefaults() {
        fetch('/nexFlow/admin/api/settings.php?action=team_defaults', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const d = data.data.defaults || {};
                const users = data.data.users || [];
                const teams = data.data.teams || [];

                ['tdOwner', 'tdDealOwner', 'tdTaskAssignee', 'lsDefaultOwner', 'tsDefaultAssignee'].forEach(selectId => {
                    const sel = document.getElementById(selectId);
                    if (sel && users.length > 0) {
                        const currentVal = sel.value;
                        sel.innerHTML = users.map(u => `<option value="${u.id}">${u.name}</option>`).join('');
                        if (currentVal) sel.value = currentVal;
                    }
                });

                const teamSel = document.getElementById('tdTeam');
                if (teamSel && teams.length > 0) {
                    const currentVal = teamSel.value;
                    teamSel.innerHTML = teams.map(t => `<option value="${t.id}">${t.team_name}</option>`).join('');
                    if (currentVal) teamSel.value = currentVal;
                }

                state.teamDefaults = {
                    defaultOwner: d.default_lead_owner_id || (users[0] ? users[0].id : ''),
                    defaultDealOwner: d.default_deal_owner_id || (users[0] ? users[0].id : ''),
                    defaultTaskAssignee: d.default_task_assignee_id || (users[0] ? users[0].id : ''),
                    defaultTeam: d.default_team_id || (teams[0] ? teams[0].id : ''),
                    defaultStatus: d.default_availability || 'Available',
                    workingHours: d.working_hours || '09:00 - 17:00',
                    quotaPeriod: d.sales_target_period || 'Monthly',
                    defaultQuota: d.default_monthly_quota || '$50,000'
                };

                populateTeamDefaultFields();
            }
        })
        .catch(err => {
            console.error("Failed to load team defaults from backend:", err);
        });
    }

    function populateTeamDefaultFields() {
        ['tdOwner', 'tdDealOwner', 'tdTaskAssignee'].forEach((id, idx) => {
            const keys = ['defaultOwner', 'defaultDealOwner', 'defaultTaskAssignee'];
            const key = keys[idx];
            const sel = document.getElementById(id);
            const targetVal = state.teamDefaults[key];
            if (sel && targetVal) {
                let match = Array.from(sel.options).find(o => String(o.value) === String(targetVal) || o.text === String(targetVal));
                if (match) sel.value = match.value;
            }
        });

        const teamSel = document.getElementById('tdTeam');
        if (teamSel && state.teamDefaults.defaultTeam) {
            const targetTeam = state.teamDefaults.defaultTeam;
            let match = Array.from(teamSel.options).find(o => String(o.value) === String(targetTeam) || o.text === String(targetTeam));
            if (match) teamSel.value = match.value;
        }

        setVal("tdStatus", state.teamDefaults.defaultStatus);
        setVal("tdHours", state.teamDefaults.workingHours);
        setVal("tdQuotaPeriod", state.teamDefaults.quotaPeriod);
        setVal("tdQuota", state.teamDefaults.defaultQuota);
    }

    function fetchRolesPreview() {
        fetch('/nexFlow/admin/api/settings.php?action=roles_preview', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.data)) {
                renderRolesPreviewTable(data.data);
            }
        })
        .catch(err => {
            console.error("Failed to load roles preview from backend:", err);
        });
    }

    function renderRolesPreviewTable(roles) {
        const tbody = document.querySelector("#settingsRolesTable tbody");
        if (!tbody || !roles || !roles.length) return;

        tbody.innerHTML = roles.map(role => `
            <tr>
                <td style="font-weight:600;color:var(--text-heading);">${role.name}</td>
                <td>${role.leads_contacts}</td>
                <td>${role.pipeline_deals}</td>
                <td>${role.reports}</td>
                <td>${role.team_management}</td>
                <td>${role.settings}</td>
            </tr>
        `).join('');
    }

    function fetchPipelineStages() {
        fetch('/nexFlow/admin/api/settings.php?action=pipeline_stages', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.data)) {
                state.pipeline = data.data.map(s => ({
                    id: s.id,
                    name: s.name,
                    color: s.color,
                    prob: parseFloat(s.probability) || 0,
                    wonLost: s.is_system ? (s.name.toLowerCase().includes('won') ? 'won' : 'lost') : 'normal',
                    is_system: s.is_system
                }));
                renderPipelineRows();
            }
        })
        .catch(err => {
            console.error("Failed to load pipeline stages from backend:", err);
        });
    }

    function fetchLeadSettings() {
        fetch('/nexFlow/admin/api/settings.php?action=lead_settings', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const l = data.data;
                const users = l.users || [];
                const statuses = l.available_statuses || ['New', 'Contacted', 'Qualified', 'Unqualified'];
                const sources = l.available_sources || ['Website', 'Referral', 'LinkedIn', 'Cold Outreach'];

                const ownerSel = document.getElementById('lsDefaultOwner');
                if (ownerSel && users.length > 0) {
                    const currentVal = ownerSel.value;
                    ownerSel.innerHTML = users.map(u => `<option value="${u.id}">${u.name}</option>`).join('');
                    if (currentVal) ownerSel.value = currentVal;
                }

                const statusSel = document.getElementById('lsDefaultStatus');
                if (statusSel && statuses.length > 0) {
                    const currentVal = l.default_lead_status || 'New';
                    statusSel.innerHTML = statuses.map(s => `<option value="${s}">${s}</option>`).join('');
                    statusSel.value = currentVal;
                }

                const sourceSel = document.getElementById('lsDefaultSource');
                if (sourceSel && sources.length > 0) {
                    const currentVal = l.default_lead_source || 'Website';
                    sourceSel.innerHTML = sources.map(s => `<option value="${s}">${s}</option>`).join('');
                    sourceSel.value = currentVal;
                }

                state.leads = {
                    defaultStatus: l.default_lead_status || 'New',
                    defaultSource: l.default_lead_source || 'Website',
                    defaultOwner: l.default_lead_owner_id || (users[0] ? users[0].id : ''),
                    scoreThreshold: l.lead_score_threshold !== undefined ? l.lead_score_threshold : 70,
                    inactivityDays: l.inactivity_threshold_days !== undefined ? l.inactivity_threshold_days : 14,
                    scoreEnabled: l.lead_scoring_enabled !== undefined ? l.lead_scoring_enabled : true,
                    duplicateDetection: l.duplicate_detection_enabled !== undefined ? l.duplicate_detection_enabled : true,
                    autoFollowup: l.auto_followup_enabled !== undefined ? l.auto_followup_enabled : true
                };

                populateLeadFields();
            }
        })
        .catch(err => {
            console.error("Failed to load lead settings from backend:", err);
        });
    }

    function populateLeadFields() {
        setVal("lsDefaultStatus", state.leads.defaultStatus);
        setVal("lsDefaultSource", state.leads.defaultSource);

        const ownerSel = document.getElementById("lsDefaultOwner");
        if (ownerSel && state.leads.defaultOwner) {
            let matchOpt = Array.from(ownerSel.options).find(o => String(o.value) === String(state.leads.defaultOwner) || o.text === String(state.leads.defaultOwner));
            if (matchOpt) ownerSel.value = matchOpt.value;
        }

        setVal("lsScoreThreshold", state.leads.scoreThreshold);
        setVal("lsInactivityDays", state.leads.inactivityDays);
        setChecked("lsScoreEnabled", state.leads.scoreEnabled);
        setChecked("lsDuplicateDetection", state.leads.duplicateDetection);
        setChecked("lsAutoFollowup", state.leads.autoFollowup);
    }

    function fetchTaskSettings() {
        fetch('/nexFlow/admin/api/settings.php?action=task_settings', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const t = data.data;
                const statuses = t.available_statuses || ['Pending', 'Not Started', 'In Progress', 'Waiting', 'Completed'];
                const priorities = t.available_priorities || ['Low', 'Medium', 'High', 'Urgent'];
                const reminders = t.available_reminders || ['none', '5 minutes before', '15 minutes before', '30 minutes before', '1 hour before', '1 day before'];
                const views = t.available_views || ['list', 'board', 'calendar'];

                const statusSel = document.getElementById('tsDefaultStatus');
                if (statusSel && statuses.length > 0) {
                    const currentVal = t.task_default_status || 'Pending';
                    statusSel.innerHTML = statuses.map(s => `<option value="${s}">${s}</option>`).join('');
                    statusSel.value = currentVal;
                }

                const prioritySel = document.getElementById('tsDefaultPriority');
                if (prioritySel && priorities.length > 0) {
                    const currentVal = t.task_default_priority || 'Medium';
                    prioritySel.innerHTML = priorities.map(p => `<option value="${p}">${p}</option>`).join('');
                    prioritySel.value = currentVal;
                }

                const reminderSel = document.getElementById('tsDefaultReminder');
                if (reminderSel && reminders.length > 0) {
                    const currentVal = t.task_default_reminder || '15 minutes before';
                    reminderSel.innerHTML = reminders.map(r => `<option value="${r}">${r === 'none' ? 'None' : r}</option>`).join('');
                    reminderSel.value = currentVal;
                }

                const viewSel = document.getElementById('tsDefaultView');
                if (viewSel && views.length > 0) {
                    const currentVal = t.task_default_view || 'list';
                    viewSel.innerHTML = views.map(v => `<option value="${v}">${v === 'list' ? 'List View' : (v === 'board' ? 'Kanban Board' : 'Calendar View')}</option>`).join('');
                    viewSel.value = currentVal;
                }

                state.tasks = {
                    defaultStatus: t.task_default_status || 'Pending',
                    defaultPriority: t.task_default_priority || 'Medium',
                    defaultReminder: t.task_default_reminder || '15 minutes before',
                    defaultView: t.task_default_view || 'list',
                    showCompleted: t.task_show_completed !== undefined ? !!t.task_show_completed : true
                };

                populateTaskFields();
            }
        })
        .catch(err => {
            console.error("Failed to load task settings from backend:", err);
        });
    }

    function populateTaskFields() {
        setVal("tsDefaultStatus", state.tasks.defaultStatus);
        setVal("tsDefaultPriority", state.tasks.defaultPriority);
        setVal("tsDefaultReminder", state.tasks.defaultReminder);
        setVal("tsDefaultView", state.tasks.defaultView);
        setChecked("tsShowCompleted", state.tasks.showCompleted);
    }

    function fetchEmailPreferences() {
        fetch('/nexFlow/admin/api/settings.php?action=email_preferences', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && (data.data || data.settings)) {
                const e = data.data || data.settings;
                state.email = {
                    senderName: e.email_sender_display_name !== undefined ? e.email_sender_display_name : '',
                    replyTo: e.email_reply_to !== undefined ? e.email_reply_to : '',
                    signature: e.email_signature !== undefined ? e.email_signature : '',
                    includeCompanySig: e.email_include_signature !== undefined ? !!e.email_include_signature : true
                };
                populateEmailPreferenceFields();
            }
        })
        .catch(err => {
            console.error("Failed to load email preferences from backend:", err);
        });
    }

    function populateEmailPreferenceFields() {
        setVal("emailSenderName", state.email.senderName);
        setVal("emailReplyTo", state.email.replyTo);
        setVal("emailSignature", state.email.signature);
        setChecked("emailIncludeSig", state.email.includeCompanySig);
    }

    function fetchCallingPreferences() {
        fetch('/nexFlow/admin/api/settings.php?action=calling_preferences', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && (data.data || data.settings)) {
                const c = data.data || data.settings;
                state.calling = {
                    ...state.calling,
                    countryCode: c.calling_default_country_code !== undefined ? c.calling_default_country_code : '',
                    defaultAvailability: c.calling_default_status !== undefined ? c.calling_default_status : '',
                    autoCallNotes: c.calling_auto_open_notes !== undefined ? !!c.calling_auto_open_notes : false,
                    showFloatingWidget: c.calling_show_floating_widget !== undefined ? !!c.calling_show_floating_widget : false
                };
                populateCallingPreferenceFields();
            }
        })
        .catch(err => {
            console.error("Failed to load calling preferences from backend:", err);
        });
    }

    function populateCallingPreferenceFields() {
        setVal("callCountryCode", state.calling.countryCode);
        setVal("callAvailability", state.calling.defaultAvailability);
        setChecked("callAutoNotes", state.calling.autoCallNotes);
        setChecked("callFloatingWidget", state.calling.showFloatingWidget);
    }

    function fetchInboxPreferences() {
        fetch('/nexFlow/admin/api/settings.php?action=inbox_preferences', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && (data.data || data.settings)) {
                const ib = data.data || data.settings;
                state.inbox = {
                    ...state.inbox,
                    defaultFolder: ib.inbox_default_folder !== undefined ? ib.inbox_default_folder : '',
                    sortOrder: ib.inbox_sort_order !== undefined ? ib.inbox_sort_order : '',
                    markReadOnOpen: ib.inbox_mark_read_on_open !== undefined ? !!ib.inbox_mark_read_on_open : false,
                    showContactContextOnClick: ib.inbox_show_contact_context !== undefined ? !!ib.inbox_show_contact_context : false
                };
                populateInboxPreferenceFields();
            }
        })
        .catch(err => {
            console.error("Failed to load inbox preferences from backend:", err);
        });
    }

    function populateInboxPreferenceFields() {
        setVal("inboxDefaultFolder", state.inbox.defaultFolder);
        setVal("inboxSortOrder", state.inbox.sortOrder);
        setChecked("inboxMarkReadOnOpen", state.inbox.markReadOnOpen);
        setChecked("inboxShowContextOnClick", state.inbox.showContactContextOnClick);
    }

    function fetchIntegrations() {
        fetch('/nexFlow/admin/api/settings.php?action=integrations', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && (data.integrations || data.data)) {
                const list = data.integrations || data.data;
                renderIntegrations(list);
            }
        })
        .catch(err => {
            console.error("Failed to load integrations from backend:", err);
        });
    }

    function renderIntegrations(list) {
        if (!Array.isArray(list)) return;

        list.forEach(item => {
            const card = document.querySelector(`.settings-integration-card[data-integration-key="${item.integration_key}"]`);
            if (!card) return;

            const badge = card.querySelector(".status-badge");
            const btn = card.querySelector("[data-integration-btn]") || card.querySelector("button");

            if (badge) {
                let badgeClass = "status-badge status-proposal";
                let badgeText = "Not Connected";
                if (item.status === 'connected') {
                    badgeClass = "status-badge status-won";
                    badgeText = "Connected";
                } else if (item.status === 'pending') {
                    badgeClass = "status-badge status-qualified";
                    badgeText = "Pending";
                } else if (item.status === 'disconnected') {
                    badgeClass = "status-badge status-lost";
                    badgeText = "Disconnected";
                } else if (item.status === 'error') {
                    badgeClass = "status-badge status-lost";
                    badgeText = "Error";
                }
                badge.className = badgeClass;
                badge.textContent = badgeText;
            }

            if (btn) {
                btn.disabled = false;
                if (item.status === 'connected') {
                    btn.textContent = "Disconnect";
                    btn.setAttribute("data-action", "disconnect");
                } else if (item.status === 'pending') {
                    btn.textContent = "Connecting...";
                    btn.disabled = true;
                    btn.setAttribute("data-action", "none");
                } else if (item.status === 'error') {
                    btn.textContent = "Retry";
                    btn.setAttribute("data-action", "connect");
                } else {
                    btn.textContent = "Connect";
                    btn.setAttribute("data-action", "connect");
                }
            }
        });
    }

    function setupIntegrationsListeners() {
        const grid = document.querySelector(".settings-integrations-grid");
        if (!grid || grid._listenersAttached) return;
        grid._listenersAttached = true;

        grid.addEventListener("click", function(e) {
            const btn = e.target.closest("[data-integration-btn], .settings-integration-footer button");
            if (!btn) return;

            const card = btn.closest(".settings-integration-card");
            if (!card) return;

            const key = card.getAttribute("data-integration-key");
            if (!key) return;

            const action = btn.getAttribute("data-action") || "connect";

            if (action === "connect") {
                btn.disabled = true;
                const params = new URLSearchParams();
                params.append("action", "connect_integration");
                params.append("integration_key", key);

                fetch("/nexFlow/admin/api/settings.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: params.toString()
                })
                .then(res => res.json())
                .then(resData => {
                    btn.disabled = false;
                    if (resData.code === "INTEGRATION_NOT_CONFIGURED" || !resData.success) {
                        showToast(resData.message || "This integration requires external credentials and backend configuration before it can be connected.", "warning");
                    } else {
                        showToast(resData.message || "Integration connected.", "success");
                    }
                    fetchIntegrations();
                })
                .catch(err => {
                    btn.disabled = false;
                    console.error("Connect error:", err);
                    showToast("This integration requires external credentials and backend configuration before it can be connected.", "warning");
                });
            } else if (action === "disconnect") {
                btn.disabled = true;
                const params = new URLSearchParams();
                params.append("action", "disconnect_integration");
                params.append("integration_key", key);

                fetch("/nexFlow/admin/api/settings.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: params.toString()
                })
                .then(res => res.json())
                .then(resData => {
                    btn.disabled = false;
                    showToast(resData.message || "Integration disconnected.", "info");
                    fetchIntegrations();
                })
                .catch(err => {
                    btn.disabled = false;
                    console.error("Disconnect error:", err);
                    showToast("Failed to disconnect integration.", "warning");
                });
            }
        });
    }

    function initSettingsPage(forceReinit) {
        const root = document.querySelector(".settings-page");
        if (!root) return;

        if (window._settingsPageInitialized && !forceReinit) return;
        window._settingsPageInitialized = true;

        loadSavedSettings();
        fetchUserProfile();
        fetchUserPreferences();
        fetchUserNotifications();
        fetchCompanyProfile();
        fetchTeamDefaults();
        fetchRolesPreview();
        fetchPipelineStages();
        fetchLeadSettings();
        fetchTaskSettings();
        fetchEmailPreferences();
        fetchCallingPreferences();
        fetchInboxPreferences();
        fetchIntegrations();
        setupIntegrationsListeners();
        setupNavigation();
        setupSearch();
        setupFormListeners();
        setupAvatarUpload();
        setupNotificationToggleListeners();
        setupPipelineEditor();
        setupCustomFields();
        setupCSVDropzone();
        setupKeyboardShortcuts();
        populateFormFields();
        loadUniversalCrmSettings();
        updateStatusBadge(false);
    }

    window.initSettingsPage = initSettingsPage;

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () { initSettingsPage(); });
    } else {
        initSettingsPage();
    }

    // Local Storage Loading
    function loadSavedSettings() {
        Object.keys(defaultState).forEach(key => {
            const saved = localStorage.getItem(STORAGE_PREFIX + key);
            if (saved) {
                try {
                    state[key] = JSON.parse(saved);
                } catch (e) {
                    console.error("Failed to parse settings key: " + key, e);
                }
            }
        });

        // Softphone integration check
        const softphonePrefs = localStorage.getItem("NexFlow_softphone_prefs");
        if (softphonePrefs) {
            try {
                const parsed = JSON.parse(softphonePrefs);
                state.calling = { ...state.calling, ...parsed };
            } catch (e) {}
        }
    }

    // Section Resolver Helper
    function resolveSectionFromHash(hash) {
        if (!hash) return "profile";
        const clean = hash.replace(/^#/, "").trim().toLowerCase();
        if (!clean) return "profile";
        if (clean === "my-profile" || clean === "profile") return "profile";

        const exists = document.getElementById("section-" + clean) ||
                       document.querySelector(`.settings-nav-item[data-section="${clean}"]`);
        if (exists) return clean;

        return "profile";
    }

    // Navigation & Tabs
    function setupNavigation() {
        const navItems = document.querySelectorAll(".settings-nav-item");
        const mobileSelect = document.getElementById("settingsMobileNavSelect");

        navItems.forEach(item => {
            item.addEventListener("click", function () {
                const targetSection = this.getAttribute("data-section");
                const targetHash = (targetSection === "profile") ? "my-profile" : targetSection;
                if (window.location.hash !== `#${targetHash}`) {
                    window.location.hash = targetHash;
                } else {
                    switchSection(targetSection, false);
                }
            });
        });

        if (mobileSelect) {
            mobileSelect.addEventListener("change", function () {
                const targetSection = this.value;
                const targetHash = (targetSection === "profile") ? "my-profile" : targetSection;
                window.location.hash = targetHash;
            });
        }

        // Handle Hash Change (Browser Back/Forward navigation & topbar dropdown links)
        window.addEventListener("hashchange", function () {
            const section = resolveSectionFromHash(window.location.hash);
            switchSection(section, false);
        });

        // Initialize active section from current URL hash
        const initialSection = resolveSectionFromHash(window.location.hash);
        switchSection(initialSection, false);
    }

    function switchSection(sectionId, updateHash = true) {
        if (!sectionId) sectionId = "profile";
        const resolvedId = resolveSectionFromHash(sectionId);

        // Update sidebar nav items & accessibility
        document.querySelectorAll(".settings-nav-item").forEach(item => {
            if (item.getAttribute("data-section") === resolvedId) {
                item.classList.add("active");
                item.setAttribute("aria-current", "page");
            } else {
                item.classList.remove("active");
                item.removeAttribute("aria-current");
            }
        });

        // Update content sections
        document.querySelectorAll(".settings-section").forEach(sec => {
            if (sec.id === "section-" + resolvedId) {
                sec.classList.add("active");
            } else {
                sec.classList.remove("active");
            }
        });

        if (resolvedId === "integrations") {
            fetchIntegrations();
        }

        // Update mobile nav select
        const mobileSelect = document.getElementById("settingsMobileNavSelect");
        if (mobileSelect) mobileSelect.value = resolvedId;

        // Scroll settings content area and window to top
        const contentArea = document.querySelector(".settings-content");
        if (contentArea) contentArea.scrollTop = 0;
        const pageArea = document.querySelector(".settings-page") || document.querySelector(".main-content");
        if (pageArea) pageArea.scrollTop = 0;
        window.scrollTo({ top: 0, behavior: "smooth" });

        // Update hash if requested
        if (updateHash) {
            const targetHash = (resolvedId === "profile") ? "my-profile" : resolvedId;
            if (window.location.hash !== `#${targetHash}`) {
                window.location.hash = targetHash;
            }
        }
    }

    // Settings Search Filter
    function setupSearch() {
        const searchInput = document.getElementById("settingsSearchInput");
        if (!searchInput) return;

        searchInput.addEventListener("input", function () {
            const q = this.value.toLowerCase().trim();
            const navItems = document.querySelectorAll(".settings-nav-item");
            const navGroups = document.querySelectorAll(".settings-nav-group");

            navItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (!q || text.includes(q)) {
                    item.classList.remove("hidden-search");
                } else {
                    item.classList.add("hidden-search");
                }
            });

            navGroups.forEach(group => {
                const visibleChild = group.querySelector(".settings-nav-item:not(.hidden-search)");
                group.style.display = visibleChild ? "block" : "none";
            });
        });
    }

    // Unsaved Change Tracking
    function setupFormListeners() {
        const content = document.querySelector(".settings-content");
        if (!content) return;

        content.addEventListener("input", function (e) {
            if (e.target.matches("input, select, textarea")) {
                markDirty();
            }
        });

        content.addEventListener("change", function (e) {
            if (e.target.matches("input, select, textarea")) {
                markDirty();
            }
        });
    }

    function markDirty() {
        if (!isDirty) {
            isDirty = true;
            updateStatusBadge(true);
        }
    }

    function updateStatusBadge(dirty) {
        const badge = document.getElementById("settingsStatusBadge");
        const saveBtns = document.querySelectorAll(".btn-save-settings");

        if (!badge) return;

        if (dirty) {
            badge.className = "settings-status-badge unsaved";
            badge.innerHTML = `<span style="width:6px;height:6px;border-radius:50%;background-color:#D97706;"></span> Unsaved changes`;
            saveBtns.forEach(btn => btn.disabled = false);
        } else {
            badge.className = "settings-status-badge saved";
            badge.innerHTML = `<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> All changes saved`;
            saveBtns.forEach(btn => btn.disabled = false);
        }
    }

    // Avatar Upload & FileReader
    function setupAvatarUpload() {
        const fileInput = document.getElementById("avatarFileInput");
        const removeBtn = document.getElementById("btnRemoveAvatar");

        if (fileInput) {
            fileInput.addEventListener("change", function () {
                const file = this.files[0];
                if (!file) return;

                if (!file.type.startsWith("image/")) {
                    showToast("Please select a valid image file (JPG, PNG, GIF, WEBP).", "warning");
                    return;
                }

                if (file.size > 2 * 1024 * 1024) {
                    showToast("Image size must be less than 2MB.", "warning");
                    return;
                }

                pendingAvatarFile = file;
                removeAvatarRequested = false;

                const reader = new FileReader();
                reader.onload = function (e) {
                    const dataUrl = e.target.result;
                    const previewContainer = document.getElementById("avatarPreviewContainer");
                    if (previewContainer) {
                        previewContainer.innerHTML = `<img src="${dataUrl}" alt="Avatar Preview" class="settings-avatar-preview" style="width:64px;height:64px;border-radius:50%;object-fit:cover;">`;
                    }
                    markDirty();
                    showToast("Photo updated preview.", "info");
                };
                reader.readAsDataURL(file);
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener("click", function () {
                pendingAvatarFile = null;
                removeAvatarRequested = true;
                state.profile.avatar = null;
                const previewContainer = document.getElementById("avatarPreviewContainer");
                if (previewContainer) {
                    const initText = state.profile.initials || "OM";
                    previewContainer.innerHTML = `<div class="settings-avatar-preview">${initText}</div>`;
                }
                markDirty();
                showToast("Photo removed.", "info");
            });
        }
    }

    // Pipeline Editor (Drag & Drop)
    function setupPipelineEditor() {
        renderPipelineRows();

        const addBtn = document.getElementById("btnAddPipelineStage");
        const resetBtn = document.getElementById("btnResetPipeline");

        if (addBtn) {
            addBtn.addEventListener("click", function () {
                const newId = "p_" + Date.now();
                state.pipeline.splice(state.pipeline.length - 2, 0, {
                    id: newId,
                    name: "New Stage",
                    color: "#3B82F6",
                    prob: 50,
                    wonLost: "normal"
                });
                renderPipelineRows();
                markDirty();
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener("click", function () {
                showConfirmModal({
                    title: "Reset Pipeline",
                    message: "Reset sales pipeline to default stages?",
                    type: "warning",
                    confirmText: "Reset Pipeline",
                    onConfirm: function() {
                        const params = new URLSearchParams();
                        params.append("action", "reset_pipeline_stages");

                        fetch("/nexFlow/admin/api/settings.php", {
                            method: "POST",
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: params.toString()
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && Array.isArray(data.data)) {
                                state.pipeline = data.data.map(s => ({
                                    id: s.id,
                                    name: s.name,
                                    color: s.color,
                                    prob: parseFloat(s.probability) || 0,
                                    wonLost: s.is_system ? (s.name.toLowerCase().includes('won') ? 'won' : 'lost') : 'normal',
                                    is_system: s.is_system
                                }));
                                renderPipelineRows();
                                isDirty = false;
                                updateStatusBadge(false);
                                showToast("Pipeline reset to default stages.", "info");
                            } else {
                                showToast(data.message || "Failed to reset pipeline.", "warning");
                            }
                        })
                        .catch(err => {
                            console.error("Reset pipeline error:", err);
                            showToast("Failed to reset pipeline.", "warning");
                        });
                    }
                });
            });
        }
    }

    function renderPipelineRows() {
        const container = document.getElementById("settingsPipelineList");
        if (!container) return;

        container.innerHTML = state.pipeline.map((stage, idx) => `
            <div class="settings-pipeline-row" data-index="${idx}" draggable="true">
                <span class="settings-drag-handle" title="Drag to reorder">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="18" x2="16" y2="18"/></svg>
                </span>
                <input type="color" class="settings-stage-color-input" value="${stage.color}" onchange="window.settingsApp.updateStage(${idx}, 'color', this.value)">
                <input type="text" class="input-control input-sm stage-name-input" value="${stage.name}" placeholder="Stage Name" oninput="window.settingsApp.updateStage(${idx}, 'name', this.value)">
                <div style="display:flex;align-items:center;gap:4px;">
                    <input type="number" class="input-control input-sm stage-prob-input" value="${stage.prob}" min="0" max="100" oninput="window.settingsApp.updateStage(${idx}, 'prob', this.value)">
                    <span style="font-size:12px;color:var(--text-muted);">%</span>
                </div>
                ${stage.wonLost === 'normal' && !stage.is_system ? `
                    <button type="button" class="btn btn-ghost btn-xs" style="color:#DC2626;" onclick="window.settingsApp.deleteStage(${idx})">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                    </button>
                ` : `
                    <span class="status-badge ${stage.wonLost === 'won' ? 'status-won' : 'status-lost'}" style="font-size:11px;">System</span>
                `}
            </div>
        `).join("");

        // Attach DnD listeners
        const rows = container.querySelectorAll(".settings-pipeline-row");
        let dragIdx = null;

        rows.forEach(row => {
            row.addEventListener("dragstart", function (e) {
                dragIdx = parseInt(this.getAttribute("data-index"));
                this.classList.add("dragging");
                e.dataTransfer.effectAllowed = "move";
            });

            row.addEventListener("dragend", function () {
                this.classList.remove("dragging");
            });

            row.addEventListener("dragover", function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = "move";
            });

            row.addEventListener("drop", function (e) {
                e.preventDefault();
                const dropIdx = parseInt(this.getAttribute("data-index"));
                if (dragIdx !== null && dragIdx !== dropIdx) {
                    const draggedItem = state.pipeline.splice(dragIdx, 1)[0];
                    state.pipeline.splice(dropIdx, 0, draggedItem);
                    renderPipelineRows();
                    markDirty();
                }
            });
        });
    }

    function updateStage(idx, key, value) {
        if (state.pipeline[idx]) {
            state.pipeline[idx][key] = key === 'prob' ? parseInt(value) || 0 : value;
            markDirty();
        }
    }

    function deleteStage(idx) {
        const stage = state.pipeline[idx];
        if (!stage) return;
        if (stage.wonLost !== 'normal' || stage.is_system) {
            showToast("System stages cannot be deleted.", "warning");
            return;
        }

        showConfirmModal({
            title: "Delete Stage",
            message: `Are you sure you want to delete stage "${stage.name}"?`,
            type: "danger",
            confirmText: "Delete Stage",
            onConfirm: function() {
                if (stage.id && !String(stage.id).startsWith("p_")) {
                    const params = new URLSearchParams();
                    params.append("action", "delete_pipeline_stage");
                    params.append("id", stage.id);

                    fetch("/nexFlow/admin/api/settings.php", {
                        method: "POST",
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: params.toString()
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            if (Array.isArray(data.data)) {
                                state.pipeline = data.data.map(s => ({
                                    id: s.id,
                                    name: s.name,
                                    color: s.color,
                                    prob: parseFloat(s.probability) || 0,
                                    wonLost: s.is_system ? (s.name.toLowerCase().includes('won') ? 'won' : 'lost') : 'normal',
                                    is_system: s.is_system
                                }));
                            } else {
                                state.pipeline.splice(idx, 1);
                            }
                            renderPipelineRows();
                            showToast(data.message || "Stage deleted successfully.", "success");
                        } else {
                            showToast(data.message || "Failed to delete stage.", "warning");
                        }
                    })
                    .catch(err => {
                        console.error("Delete stage error:", err);
                        showToast("Failed to delete stage.", "warning");
                    });
                } else {
                    state.pipeline.splice(idx, 1);
                    renderPipelineRows();
                    markDirty();
                }
            }
        });
    }

    function fetchCustomFields(targetObject) {
        let url = '/nexFlow/admin/api/settings.php?action=custom_fields';
        if (targetObject) {
            url += '&target_object=' + encodeURIComponent(targetObject);
        }
        fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.data)) {
                state.customFields = data.data;
                renderCustomFieldsTable();
            }
        })
        .catch(err => {
            console.error("Failed to load custom fields from backend:", err);
        });
    }

    // Custom Fields Drawer & Logic
    function setupCustomFields() {
        fetchCustomFields();

        const tabs = document.querySelectorAll(".settings-cf-tab");
        tabs.forEach(tab => {
            tab.addEventListener("click", function () {
                tabs.forEach(t => t.classList.remove("active"));
                this.classList.add("active");
                activeCustomFieldsTab = this.getAttribute("data-object");
                renderCustomFieldsTable();
            });
        });

        const openBtn = document.getElementById("btnOpenCustomFieldDrawer");
        const closeBtn = document.getElementById("btnCloseCustomFieldDrawer");
        const drawer = document.getElementById("customFieldDrawer");
        const form = document.getElementById("customFieldForm");
        const typeSelect = document.getElementById("cfTypeSelect");
        const optionsGroup = document.getElementById("cfOptionsGroup");

        if (typeSelect && optionsGroup) {
            const checkType = function() {
                const val = typeSelect.value;
                if (val === "Dropdown" || val === "Multi Select" || val === "Radio") {
                    optionsGroup.style.display = "block";
                } else {
                    optionsGroup.style.display = "none";
                }
            };
            typeSelect.addEventListener("change", checkType);
            checkType();
        }

        if (openBtn && drawer) {
            openBtn.addEventListener("click", function () {
                drawer.classList.add("show");
            });
        }

        if (closeBtn && drawer) {
            closeBtn.addEventListener("click", function () {
                drawer.classList.remove("show");
            });
        }

        if (form) {
            form.addEventListener("submit", function (e) {
                e.preventDefault();
                const label = document.getElementById("cfLabelInput").value.trim();
                const obj = document.getElementById("cfObjectSelect").value;
                const type = document.getElementById("cfTypeSelect").value;
                const optionsInput = document.getElementById("cfOptionsInput") ? document.getElementById("cfOptionsInput").value.trim() : "";
                const req = document.getElementById("cfRequiredToggle") ? document.getElementById("cfRequiredToggle").checked : false;
                const active = document.getElementById("cfActiveToggle") ? document.getElementById("cfActiveToggle").checked : true;

                if (!label) {
                    showToast("Please enter a field label.", "warning");
                    return;
                }

                const params = new URLSearchParams();
                params.append("action", "custom_fields");
                params.append("field_label", label);
                params.append("target_object", obj);
                params.append("field_type", type);
                params.append("is_required", req ? "1" : "0");
                params.append("is_active", active ? "1" : "0");
                if (optionsInput) {
                    params.append("options", optionsInput);
                }

                fetch("/nexFlow/admin/api/settings.php", {
                    method: "POST",
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data) {
                        if (Array.isArray(data.data.fields)) {
                            state.customFields = data.data.fields;
                        }
                        fetchCustomFields();
                        drawer.classList.remove("show");
                        form.reset();
                        if (optionsGroup) optionsGroup.style.display = "none";
                        showToast(data.message || `Custom field '${label}' created successfully.`, "success");
                    } else {
                        showToast(data.message || "Failed to create custom field.", "warning");
                    }
                })
                .catch(err => {
                    console.error("Create custom field error:", err);
                    showToast("Failed to create custom field.", "warning");
                });
            });
        }
    }

    function renderCustomFieldsTable() {
        const tbody = document.getElementById("settingsCustomFieldsTbody");
        if (!tbody) return;

        const filtered = state.customFields.filter(cf => (cf.object || cf.target_object) === activeCustomFieldsTab);

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">No custom fields for ${activeCustomFieldsTab}. Click "+ Create Custom Field" to add one.</td></tr>`;
            return;
        }

        tbody.innerHTML = filtered.map((cf, idx) => `
            <tr>
                <td style="font-weight:600;color:var(--text-heading);">${cf.name || cf.field_label}</td>
                <td><span class="status-badge status-new">${cf.object || cf.target_object}</span></td>
                <td>${cf.type || cf.field_type}</td>
                <td>${(cf.required || cf.is_required) ? '<span style="color:#059669;font-weight:600;">Yes</span>' : '<span style="color:var(--text-muted);">No</span>'}</td>
                <td>${(cf.active || cf.is_active) ? '<span style="color:#059669;font-weight:600;">Active</span>' : '<span style="color:#DC2626;font-weight:600;">Inactive</span>'}</td>
                <td>
                    <button type="button" class="btn btn-ghost btn-xs" style="color:#DC2626;" onclick="window.settingsApp.deleteCustomField('${cf.id}')">Delete</button>
                </td>
            </tr>
        `).join("");
    }

    function deleteCustomField(id) {
        const cf = state.customFields.find(f => String(f.id) === String(id));
        const label = cf ? (cf.name || cf.field_label) : 'this custom field';

        showConfirmModal({
            title: "Remove Custom Field",
            message: `Are you sure you want to delete the custom field "${label}"?`,
            type: "danger",
            confirmText: "Delete Field",
            onConfirm: function() {
                const params = new URLSearchParams();
                params.append("action", "delete_custom_field");
                params.append("id", id);

                fetch("/nexFlow/admin/api/settings.php", {
                    method: "POST",
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        fetchCustomFields();
                        showToast(data.message || "Custom field deleted successfully.", "success");
                    } else {
                        showToast(data.message || "Failed to delete custom field.", "warning");
                    }
                })
                .catch(err => {
                    console.error("Delete custom field error:", err);
                    showToast("Failed to delete custom field.", "warning");
                });
            }
        });
    }

    // CSV Dropzone & Import Preview
    function setupCSVDropzone() {
        const dropzone = document.getElementById("csvDropzone");
        const fileInput = document.getElementById("csvFileInput");
        const summaryBox = document.getElementById("csvImportSummary");

        if (!dropzone || !fileInput) return;

        dropzone.addEventListener("click", () => fileInput.click());

        dropzone.addEventListener("dragover", (e) => {
            e.preventDefault();
            dropzone.classList.add("drag-over");
        });

        dropzone.addEventListener("dragleave", () => {
            dropzone.classList.remove("drag-over");
        });

        dropzone.addEventListener("drop", (e) => {
            e.preventDefault();
            dropzone.classList.remove("drag-over");
            if (e.dataTransfer.files.length) {
                handleCSVFile(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener("change", function () {
            if (this.files.length) {
                handleCSVFile(this.files[0]);
            }
        });

        function handleCSVFile(file) {
            if (!file.name.endsWith(".csv")) {
                showToast("Please upload a .csv file.", "warning");
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                const text = e.target.result;
                const lines = text.split("\n").filter(l => l.trim().length > 0);
                const headers = lines.length ? lines[0].split(",") : [];

                if (summaryBox) {
                    summaryBox.style.display = "block";
                    summaryBox.innerHTML = `
                        <div class="settings-banner settings-banner-info" style="margin-bottom:0;">
                            <div>
                                <strong>File Selected:</strong> ${file.name} (${(file.size / 1024).toFixed(1)} KB)<br>
                                <strong>Parsed Rows:</strong> ${Math.max(0, lines.length - 1)} records found<br>
                                <strong>Detected Headers:</strong> ${headers.map(h => `<code>${h.trim()}</code>`).join(", ")}
                            </div>
                        </div>
                    `;
                }
                showToast("CSV parsed for local frontend preview.", "info");
            };
            reader.readAsText(file);
        }
    }

    // Populate UI fields from state
    function populateFormFields() {
        populateProfileFields();
        populatePreferencesFields();
        populateNotificationFields();

        // Company
        setVal("compName", state.company.name);
        setVal("compWebsite", state.company.website);
        setVal("compIndustry", state.company.industry);
        setVal("compSize", state.company.size);
        setVal("compPhone", state.company.phone);
        setVal("compEmail", state.company.email);
        setVal("compCountry", state.company.country);
        setVal("compAddress", state.company.address);
        setVal("compCity", state.company.city);
        setVal("compState", state.company.state);
        setVal("compZip", state.company.zip);
        setVal("compCurrency", state.company.currency);
        setVal("compFiscalStart", state.company.fiscalStart);
        setVal("compHours", state.company.hours);

        // Team Defaults
        setVal("tdOwner", state.teamDefaults.defaultOwner);
        setVal("tdDealOwner", state.teamDefaults.defaultDealOwner);
        setVal("tdTaskAssignee", state.teamDefaults.defaultTaskAssignee);
        setVal("tdTeam", state.teamDefaults.defaultTeam);
        setVal("tdStatus", state.teamDefaults.defaultStatus);
        setVal("tdHours", state.teamDefaults.workingHours);
        setVal("tdQuotaPeriod", state.teamDefaults.quotaPeriod);
        setVal("tdQuota", state.teamDefaults.defaultQuota);

        // Lead settings
        setVal("lsDefaultStatus", state.leads.defaultStatus);
        setVal("lsDefaultSource", state.leads.defaultSource);
        setVal("lsDefaultOwner", state.leads.defaultOwner);
        setChecked("lsScoreEnabled", state.leads.scoreEnabled);
        setVal("lsScoreThreshold", state.leads.scoreThreshold);
        setChecked("lsDuplicateDetection", state.leads.duplicateDetection);
        setVal("lsInactivityDays", state.leads.inactivityDays);
        setChecked("lsAutoFollowup", state.leads.autoFollowup);

        // Task settings
        setVal("tsDefaultStatus", state.tasks.defaultStatus);
        setVal("tsDefaultPriority", state.tasks.defaultPriority);
        setVal("tsDefaultReminder", state.tasks.defaultReminder);
        setChecked("tsShowCompleted", state.tasks.showCompleted);
        setVal("tsDefaultView", state.tasks.defaultView);
        setVal("tsDefaultAssignee", state.tasks.defaultAssignee);

        // Email settings
        setVal("emailSenderName", state.email.senderName);
        setVal("emailReplyTo", state.email.replyTo);
        setVal("emailSignature", state.email.signature);
        setChecked("emailIncludeSig", state.email.includeCompanySig);
        setChecked("emailTrackOpens", state.email.trackOpens);
        setChecked("emailTrackClicks", state.email.trackClicks);

        // Calling settings
        setVal("callCountryCode", state.calling.countryCode);
        setVal("callAvailability", state.calling.defaultAvailability);
        setChecked("callAutoNotes", state.calling.autoCallNotes);
        setChecked("callFloatingWidget", state.calling.showFloatingWidget);
        setChecked("callTimerDisplay", state.calling.timerDisplay);
        setVal("callRecentLimit", state.calling.recentCallsLimit);
        setChecked("callSimulatedIncoming", state.calling.simulatedIncoming);

        // Inbox settings
        setVal("inboxDefaultFolder", state.inbox.defaultFolder);
        setVal("inboxSortOrder", state.inbox.sortOrder);
        setChecked("inboxMarkReadOnOpen", state.inbox.markReadOnOpen);
        setChecked("inboxShowContextOnClick", state.inbox.showContactContextOnClick);
        setVal("inboxDefaultReplyChannel", state.inbox.defaultReplyChannel);
        setChecked("inboxShowArchived", state.inbox.showArchived);
        setVal("inboxDensity", state.inbox.density);
    }

    // Save All Settings Action
    function saveAllSettings() {
        // Validate Profile
        const fn = getVal("profileFirstName");
        const ln = getVal("profileLastName");
        const em = getVal("profileEmail");

        if (!fn || !ln) {
            showToast("First and last name are required.", "warning");
            switchSection("profile");
            return;
        }

        if (em && !em.includes("@")) {
            showToast("Please enter a valid email address.", "warning");
            switchSection("profile");
            return;
        }

        // Validate Lead Settings Thresholds
        const lsScoreThresh = parseInt(getVal("lsScoreThreshold"));
        if (isNaN(lsScoreThresh) || lsScoreThresh < 0 || lsScoreThresh > 100) {
            showToast("Lead score threshold must be between 0 and 100.", "warning");
            switchSection("leads");
            return;
        }

        const lsInactDays = parseInt(getVal("lsInactivityDays"));
        if (isNaN(lsInactDays) || lsInactDays < 0) {
            showToast("Inactivity threshold days must be 0 or greater.", "warning");
            switchSection("leads");
            return;
        }

        // Validate Email Preferences Reply-to Email Address
        const replyToVal = getVal("emailReplyTo").trim();
        if (replyToVal && (!replyToVal.includes("@") || !replyToVal.includes("."))) {
            showToast("Please enter a valid reply-to email address.", "warning");
            switchSection("email");
            return;
        }

        // Prepare Profile FormData for PHP Backend
        const formData = new FormData();
        formData.append("first_name", fn);
        formData.append("last_name", ln);
        formData.append("display_name", getVal("profileDisplayName") || `${fn} ${ln}`);
        formData.append("email", em);
        formData.append("phone", getVal("profilePhone"));
        formData.append("job_title", getVal("profileJobTitle"));
        formData.append("department", getVal("profileDepartment"));
        formData.append("location", getVal("profileLocation"));
        formData.append("timezone", getVal("profileTimeZone"));
        formData.append("language", getVal("profileLanguage"));
        formData.append("short_bio", getVal("profileBio"));
        formData.append("linkedin_url", getVal("profileLinkedin"));

        if (pendingAvatarFile) {
            formData.append("photo", pendingAvatarFile);
        }
        if (removeAvatarRequested) {
            formData.append("remove_photo", "1");
        }

        fetch("/nexFlow/admin/api/profile.php", {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(resData => {
            if (resData.success && resData.data) {
                const p = resData.data;
                state.profile = {
                    firstName: p.first_name || '',
                    lastName: p.last_name || '',
                    displayName: p.display_name || p.name || '',
                    email: p.email || '',
                    phone: p.phone || '',
                    jobTitle: p.job_title || '',
                    department: p.department || '',
                    location: p.location || '',
                    timezone: p.timezone || '',
                    language: p.language || '',
                    bio: p.short_bio || '',
                    linkedin: p.linkedin_url || '',
                    avatar: p.photo_url || null,
                    initials: p.initials || 'OM'
                };

                pendingAvatarFile = null;
                removeAvatarRequested = false;

                updateUserHeaderElements(p);
                populateProfileFields();
            }

            // Save Preferences to API
            const prefParams = new URLSearchParams();
            prefParams.append("language", getVal("prefLanguage"));
            prefParams.append("timezone", getVal("prefTimeZone"));
            prefParams.append("date_format", getVal("prefDateFormat"));
            prefParams.append("time_format", getVal("prefTimeFormat"));
            prefParams.append("week_starts_on", getVal("prefWeekStartsOn"));
            prefParams.append("currency", getVal("prefCurrency"));
            prefParams.append("number_format", getVal("prefNumberFormat"));
            prefParams.append("landing_page", getVal("prefDefaultLanding"));
            prefParams.append("table_rows", getVal("prefPageSize"));
            prefParams.append("interface_density", getVal("prefDensity"));

            return fetch("/nexFlow/admin/api/preferences.php", {
                method: "POST",
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: prefParams.toString()
            });
        })
        .then(res => res ? res.json() : null)
        .then(prefRes => {
            if (prefRes && prefRes.success && prefRes.data) {
                const pr = prefRes.data;
                state.preferences = {
                    language: pr.language,
                    timeZone: pr.timezone,
                    dateFormat: pr.date_format,
                    timeFormat: pr.time_format,
                    weekStartsOn: pr.week_starts_on,
                    currency: pr.currency,
                    numberFormat: pr.number_format,
                    defaultLanding: pr.landing_page,
                    pageSize: String(pr.table_rows),
                    density: pr.interface_density
                };
                populatePreferencesFields();
            }

            // Save Company Profile to API
            const compParams = new URLSearchParams();
            compParams.append("action", "company_profile");
            compParams.append("name", getVal("compName"));
            compParams.append("website", getVal("compWebsite"));
            compParams.append("industry", getVal("compIndustry"));
            compParams.append("company_size", getVal("compSize"));
            compParams.append("phone", getVal("compPhone"));
            compParams.append("business_email", getVal("compEmail"));
            compParams.append("country", getVal("compCountry"));
            compParams.append("city", getVal("compCity"));
            compParams.append("state_region", getVal("compState"));
            compParams.append("postal_code", getVal("compZip"));
            compParams.append("fiscal_year_start", getVal("compFiscalStart"));
            compParams.append("business_hours", getVal("compHours"));

            return fetch("/nexFlow/admin/api/settings.php", {
                method: "POST",
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: compParams.toString()
            });
        })
        .then(res => res ? res.json() : null)
        .then(compRes => {
            if (compRes && compRes.success && compRes.data) {
                const c = compRes.data;
                state.company = {
                    name: c.name,
                    website: c.website,
                    industry: c.industry,
                    size: c.company_size,
                    phone: c.phone,
                    email: c.business_email,
                    country: c.country,
                    city: c.city,
                    state: c.state_region,
                    zip: c.postal_code,
                    fiscalStart: c.fiscal_year_start,
                    hours: c.business_hours
                };
                populateCompanyFields();
            }

            // Save Team Defaults to API
            const tdParams = new URLSearchParams();
            tdParams.append("action", "team_defaults");
            tdParams.append("default_lead_owner_id", getVal("tdOwner"));
            tdParams.append("default_deal_owner_id", getVal("tdDealOwner"));
            tdParams.append("default_task_assignee_id", getVal("tdTaskAssignee"));
            tdParams.append("default_team_id", getVal("tdTeam"));
            tdParams.append("default_availability", getVal("tdStatus"));
            tdParams.append("working_hours", getVal("tdHours"));
            tdParams.append("sales_target_period", getVal("tdQuotaPeriod"));
            tdParams.append("default_monthly_quota", getVal("tdQuota"));

            return fetch("/nexFlow/admin/api/settings.php", {
                method: "POST",
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: tdParams.toString()
            });
        })
        .then(res => res ? res.json() : null)
        .then(tdRes => {
            if (tdRes && tdRes.success && tdRes.data) {
                const d = tdRes.data;
                state.teamDefaults = {
                    defaultOwner: d.default_lead_owner_id,
                    defaultDealOwner: d.default_deal_owner_id,
                    defaultTaskAssignee: d.default_task_assignee_id,
                    defaultTeam: d.default_team_id,
                    defaultStatus: d.default_availability,
                    workingHours: d.working_hours,
                    quotaPeriod: d.sales_target_period,
                    defaultQuota: d.default_monthly_quota
                };
                populateTeamDefaultFields();
            }

            // Save Sales Pipeline Stages to API
            const pipeParams = new URLSearchParams();
            pipeParams.append("action", "pipeline_stages");
            pipeParams.append("stages_json", JSON.stringify(state.pipeline.map(s => ({
                id: (String(s.id).startsWith("p_") ? null : s.id),
                name: s.name,
                color: s.color,
                probability: s.prob
            }))));

            return fetch("/nexFlow/admin/api/settings.php", {
                method: "POST",
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: pipeParams.toString()
            });
        })
        .then(res => res ? res.json() : null)
        .then(pipeRes => {
            if (pipeRes) {
                if (pipeRes.success && Array.isArray(pipeRes.data)) {
                    state.pipeline = pipeRes.data.map(s => ({
                        id: s.id,
                        name: s.name,
                        color: s.color,
                        prob: parseFloat(s.probability) || 0,
                        wonLost: s.is_system ? (s.name.toLowerCase().includes('won') ? 'won' : 'lost') : 'normal',
                        is_system: s.is_system
                    }));
                    renderPipelineRows();
                } else if (!pipeRes.success) {
                    showToast(pipeRes.message || "Failed to save pipeline stages.", "warning");
                }
            }

            // Save Lead Settings to API
            const lsParams = new URLSearchParams();
            lsParams.append("action", "lead_settings");
            lsParams.append("default_lead_status", getVal("lsDefaultStatus"));
            lsParams.append("default_lead_source", getVal("lsDefaultSource"));
            lsParams.append("default_lead_owner_id", getVal("lsDefaultOwner"));
            lsParams.append("lead_score_threshold", getVal("lsScoreThreshold"));
            lsParams.append("inactivity_threshold_days", getVal("lsInactivityDays"));
            lsParams.append("lead_scoring_enabled", getChecked("lsScoreEnabled") ? "1" : "0");
            lsParams.append("duplicate_detection_enabled", getChecked("lsDuplicateDetection") ? "1" : "0");
            lsParams.append("auto_followup_enabled", getChecked("lsAutoFollowup") ? "1" : "0");

            return fetch("/nexFlow/admin/api/settings.php", {
                method: "POST",
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: lsParams.toString()
            });
        })
        .then(res => res ? res.json() : null)
        .then(lsRes => {
            if (lsRes) {
                if (lsRes.success && lsRes.data) {
                    const l = lsRes.data;
                    state.leads = {
                        defaultStatus: l.default_lead_status,
                        defaultSource: l.default_lead_source,
                        defaultOwner: l.default_lead_owner_id,
                        scoreThreshold: l.lead_score_threshold,
                        inactivityDays: l.inactivity_threshold_days,
                        scoreEnabled: l.lead_scoring_enabled,
                        duplicateDetection: l.duplicate_detection_enabled,
                        autoFollowup: l.auto_followup_enabled
                    };
                    populateLeadFields();
                } else if (!lsRes.success) {
                    showToast(lsRes.message || "Failed to save lead settings.", "warning");
                }
            }

            // Save Task Settings to API
            const tsParams = new URLSearchParams();
            tsParams.append("action", "task_settings");
            tsParams.append("task_default_status", getVal("tsDefaultStatus"));
            tsParams.append("task_default_priority", getVal("tsDefaultPriority"));
            tsParams.append("task_default_reminder", getVal("tsDefaultReminder"));
            tsParams.append("task_default_view", getVal("tsDefaultView"));
            tsParams.append("task_show_completed", getChecked("tsShowCompleted") ? "1" : "0");

            return fetch("/nexFlow/admin/api/settings.php", {
                method: "POST",
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: tsParams.toString()
            });
        })
        .then(res => res ? res.json() : null)
        .then(tsRes => {
            if (tsRes) {
                if (tsRes.success && tsRes.data) {
                    const t = tsRes.data;
                    state.tasks = {
                        defaultStatus: t.task_default_status,
                        defaultPriority: t.task_default_priority,
                        defaultReminder: t.task_default_reminder,
                        defaultView: t.task_default_view,
                        showCompleted: !!t.task_show_completed
                    };
                    populateTaskFields();
                } else if (!tsRes.success) {
                    showToast(tsRes.message || "Failed to save task settings.", "warning");
                }
            }

            // Save Email Preferences to API
            const emailParams = new URLSearchParams();
            emailParams.append("action", "email_preferences");
            emailParams.append("email_sender_display_name", getVal("emailSenderName"));
            emailParams.append("email_reply_to", getVal("emailReplyTo"));
            emailParams.append("email_signature", getVal("emailSignature"));
            emailParams.append("email_include_signature", getChecked("emailIncludeSig") ? "1" : "0");

            return fetch("/nexFlow/admin/api/settings.php", {
                method: "POST",
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: emailParams.toString()
            });
        })
        .then(res => res ? res.json() : null)
        .then(emailRes => {
            if (emailRes) {
                if (emailRes.success && (emailRes.data || emailRes.settings)) {
                    const e = emailRes.data || emailRes.settings;
                    state.email = {
                        senderName: e.email_sender_display_name,
                        replyTo: e.email_reply_to,
                        signature: e.email_signature,
                        includeCompanySig: !!e.email_include_signature
                    };
                    populateEmailPreferenceFields();
                } else if (!emailRes.success) {
                    showToast(emailRes.message || "Failed to save email preferences.", "warning");
                }
            }

            // Save Calling Preferences to API
            const callingParams = new URLSearchParams();
            callingParams.append("action", "calling_preferences");
            callingParams.append("calling_default_country_code", getVal("callCountryCode"));
            callingParams.append("calling_default_status", getVal("callAvailability"));
            callingParams.append("calling_auto_open_notes", getChecked("callAutoNotes") ? "1" : "0");
            callingParams.append("calling_show_floating_widget", getChecked("callFloatingWidget") ? "1" : "0");

            return fetch("/nexFlow/admin/api/settings.php", {
                method: "POST",
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: callingParams.toString()
            });
        })
        .then(res => res ? res.json() : null)
        .then(callingRes => {
            if (callingRes) {
                if (callingRes.success && (callingRes.data || callingRes.settings)) {
                    const c = callingRes.data || callingRes.settings;
                    state.calling = {
                        ...state.calling,
                        countryCode: c.calling_default_country_code !== undefined ? c.calling_default_country_code : '',
                        defaultAvailability: c.calling_default_status !== undefined ? c.calling_default_status : '',
                        autoCallNotes: c.calling_auto_open_notes !== undefined ? !!c.calling_auto_open_notes : false,
                        showFloatingWidget: c.calling_show_floating_widget !== undefined ? !!c.calling_show_floating_widget : false
                    };
                    populateCallingPreferenceFields();
                } else if (!callingRes.success) {
                    showToast(callingRes.message || "Failed to save calling preferences.", "warning");
                }
            }

            // Save Inbox Preferences to API
            const inboxParams = new URLSearchParams();
            inboxParams.append("action", "inbox_preferences");
            inboxParams.append("inbox_default_folder", getVal("inboxDefaultFolder"));
            inboxParams.append("inbox_sort_order", getVal("inboxSortOrder"));
            inboxParams.append("inbox_mark_read_on_open", getChecked("inboxMarkReadOnOpen") ? "1" : "0");
            inboxParams.append("inbox_show_contact_context", getChecked("inboxShowContextOnClick") ? "1" : "0");

            return fetch("/nexFlow/admin/api/settings.php", {
                method: "POST",
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: inboxParams.toString()
            });
        })
        .then(res => res ? res.json() : null)
        .then(inboxRes => {
            if (inboxRes) {
                if (inboxRes.success && (inboxRes.data || inboxRes.settings)) {
                    const ib = inboxRes.data || inboxRes.settings;
                    state.inbox = {
                        ...state.inbox,
                        defaultFolder: ib.inbox_default_folder !== undefined ? ib.inbox_default_folder : '',
                        sortOrder: ib.inbox_sort_order !== undefined ? ib.inbox_sort_order : '',
                        markReadOnOpen: ib.inbox_mark_read_on_open !== undefined ? !!ib.inbox_mark_read_on_open : false,
                        showContactContextOnClick: ib.inbox_show_contact_context !== undefined ? !!ib.inbox_show_contact_context : false
                    };
                    populateInboxPreferenceFields();
                } else if (!inboxRes.success) {
                    showToast(inboxRes.message || "Failed to save inbox preferences.", "warning");
                }
            }

            isDirty = false;
            updateStatusBadge(false);
            showToast("Settings saved successfully.", "success");
        })
        .catch(err => {
            console.error("Save settings error:", err);
            showToast("An error occurred while saving settings.", "warning");
        });
    }

    function resetDemoChanges() {
        showConfirmModal({
            title: "Reset Preferences",
            message: "Reset your personal Preferences to application defaults?",
            type: "warning",
            confirmText: "Reset Preferences",
            onConfirm: function() {
                const params = new URLSearchParams();
                params.append("action", "reset");

                fetch("/nexFlow/admin/api/preferences.php", {
                    method: "POST",
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                })
                .then(res => res.json())
                .then(resData => {
                    if (resData.success && resData.data) {
                        const p = resData.data;
                        state.preferences = {
                            language: p.language,
                            timeZone: p.timezone,
                            dateFormat: p.date_format,
                            timeFormat: p.time_format,
                            weekStartsOn: p.week_starts_on,
                            currency: p.currency,
                            numberFormat: p.number_format,
                            defaultLanding: p.landing_page,
                            pageSize: String(p.table_rows),
                            density: p.interface_density
                        };
                        populatePreferencesFields();
                        isDirty = false;
                        updateStatusBadge(false);
                        showToast("Preferences reset to default values.", "info");
                    } else {
                        showToast(resData.message || "Failed to reset preferences.", "warning");
                    }
                })
                .catch(err => {
                    console.error("Reset error:", err);
                    showToast("Error resetting preferences.", "warning");
                });
            }
        });
    }

    // Export Helpers
    function exportCSV(type) {
        let headers = [];
        let rows = [];

        if (type === 'leads') {
            headers = ["ID", "Name", "Email", "Phone", "Company", "Status", "Score", "Value", "Source", "Assignee"];
            rows = (window.SETTINGS_MOCK_DATA?.leads || []).map(l => [l.id, l.name, l.email, l.phone, l.company, l.status, l.score, l.value, l.source, l.assignee]);
        } else if (type === 'contacts') {
            headers = ["ID", "Name", "Title", "Company", "Email", "Phone", "Owner", "Status"];
            rows = (window.SETTINGS_MOCK_DATA?.contacts || []).map(c => [c.id, c.name, c.title, c.company, c.email, c.phone, c.owner, c.status]);
        } else if (type === 'companies') {
            headers = ["ID", "Company", "Domain", "Industry", "Size", "Revenue", "Owner"];
            rows = (window.SETTINGS_MOCK_DATA?.companies || []).map(c => [c.id, c.name, c.domain, c.industry, c.size, c.revenue, c.owner]);
        }

        if (!rows.length) {
            showToast(`No mock data available for ${type} export.`, "warning");
            return;
        }

        let csvContent = "data:text/csv;charset=utf-8," + [headers.join(","), ...rows.map(e => e.map(cell => `"${(cell || '').toString().replace(/"/g, '""')}"`).join(","))].join("\n");
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `NexFlow_${type}_export_${Date.now()}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        showToast(`${type.toUpperCase()} exported to CSV.`, "success");
    }

    function exportJSONBackup() {
        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(state, null, 2));
        const downloadAnchor = document.createElement("a");
        downloadAnchor.setAttribute("href", dataStr);
        downloadAnchor.setAttribute("download", `NexFlow_settings_backup_${Date.now()}.json`);
        document.body.appendChild(downloadAnchor);
        downloadAnchor.click();
        downloadAnchor.remove();

        showToast("Settings exported as JSON backup.", "success");
    }

    // Safe Database-Backed Reset All Settings to Factory Default
    function resetAllSettings() {
        showConfirmModal({
            title: "Reset All Settings to Factory Default",
            message: "Are you sure you want to reset all workspace settings to factory default? All company profile, pipeline stages, notifications, and integration configurations will be restored to neutral system defaults.\n\nAll CRM business records (leads, contacts, companies, deals, invoices, projects, tasks) will remain completely intact.",
            type: "danger",
            confirmText: "Reset All Settings",
            onConfirm: function() {
                const btn = document.querySelector("#section-danger button[onclick*='resetAllSettings'], #section-danger button[onclick*='clearLocalDemoData']");
                const originalText = btn ? btn.textContent : '';
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = "Resetting...";
                }

                fetch('/nexFlow/admin/api/settings.php?action=reset_all_settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        // Clear obsolete local storage overrides
                        const keysToRemove = [];
                        for (let i = 0; i < localStorage.length; i++) {
                            const key = localStorage.key(i);
                            if (key && (key.startsWith("NexFlow") || key.startsWith("nexflow") || key.startsWith("leadflow"))) {
                                keysToRemove.push(key);
                            }
                        }
                        keysToRemove.forEach(k => localStorage.removeItem(k));

                        showToast("Settings reset to factory default successfully.", "success");
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        if (btn) {
                            btn.disabled = false;
                            btn.textContent = originalText;
                        }
                        showToast((data && data.message) || "Failed to reset settings.", "error");
                    }
                })
                .catch(err => {
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = originalText;
                    }
                    showToast("An error occurred while resetting settings.", "error");
                });
            }
        });
    }

    // Safe Database-Backed Delete Workspace (Permanent & Irreversible)
    function deleteWorkspace() {
        if (!window.NexFlowModal || typeof window.NexFlowModal.prompt !== 'function') {
            alert('Modal system unavailable.');
            return;
        }

        NexFlowModal.prompt({
            title: "Delete Workspace",
            message: "WARNING: This action is permanent and IRREVERSIBLE.\nAll workspace data, team members, deals, contacts, invoices, and configuration will be permanently destroyed.\n\nPlease type DELETE to confirm deletion:",
            label: 'Type "DELETE" to confirm:',
            placeholder: "DELETE",
            type: "danger",
            confirmText: "Permanently Delete Workspace",
            onConfirm: function(val) {
                if ((val || '').trim() !== 'DELETE') {
                    showToast("Workspace deletion cancelled: You must type DELETE in all caps to confirm.", "error");
                    return;
                }

                const deleteBtn = document.querySelector("#section-danger button[onclick*='deleteWorkspace']");
                const originalText = deleteBtn ? deleteBtn.textContent : '';
                if (deleteBtn) {
                    deleteBtn.disabled = true;
                    deleteBtn.textContent = "Deleting Workspace...";
                }

                const bodyParams = new URLSearchParams();
                bodyParams.append('action', 'delete_workspace');
                bodyParams.append('confirm_text', 'DELETE');

                fetch('/nexFlow/admin/api/settings.php?action=delete_workspace', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: bodyParams.toString()
                })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        try {
                            localStorage.clear();
                            sessionStorage.clear();
                        } catch (e) {}

                        showToast("Workspace deleted. Redirecting...", "info");
                        const redirectUrl = (data.data && data.data.redirect_url) ? data.data.redirect_url : '/nexFlow/admin/admin-login.php';
                        setTimeout(() => {
                            window.location.href = redirectUrl;
                        }, 1200);
                    } else {
                        if (deleteBtn) {
                            deleteBtn.disabled = false;
                            deleteBtn.textContent = originalText;
                        }
                        showToast((data && data.message) || "Failed to delete workspace.", "error");
                    }
                })
                .catch(err => {
                    if (deleteBtn) {
                        deleteBtn.disabled = false;
                        deleteBtn.textContent = originalText;
                    }
                    showToast("An error occurred while deleting workspace.", "error");
                });
            }
        });
    }


    function setupKeyboardShortcuts() {
        document.addEventListener("keydown", function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === "s") {
                const root = document.querySelector(".settings-page");
                if (root) {
                    e.preventDefault();
                    saveAllSettings();
                }
            }

            if (e.key === "Escape") {
                const drawer = document.getElementById("customFieldDrawer");
                if (drawer && drawer.classList.contains("show")) {
                    drawer.classList.remove("show");
                }
            }
        });
    }

    // Toast Notification System
    function showToast(message, type = "info") {
        let container = document.querySelector(".settings-toast-container");
        if (!container) {
            container = document.createElement("div");
            container.className = "settings-toast-container";
            document.body.appendChild(container);
        }

        const iconMap = {
            success: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
            info: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`,
            warning: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`
        };

        const toast = document.createElement("div");
        toast.className = `settings-toast ${type}`;
        toast.innerHTML = `${iconMap[type] || iconMap.info} <span>${message}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = "0";
            toast.style.transition = "opacity 0.25s ease";
            setTimeout(() => toast.remove(), 250);
        }, 3000);
    }

    // Helper Utility Functions
    function getVal(id) {
        const el = document.getElementById(id);
        return el ? el.value : "";
    }
    function setVal(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val || "";
    }
    function getChecked(id) {
        const el = document.getElementById(id);
        return el ? el.checked : false;
    }
    function setChecked(id, val) {
        const el = document.getElementById(id);
        if (el) el.checked = !!val;
    }

    // Universal CRM Helper Methods
    function loadUniversalCrmSettings() {
        if (!window.universalCrm) return;
        const config = window.universalCrm.getConfig();
        const presetKey = config.preset || 'standard';
        
        document.querySelectorAll(".preset-card").forEach(card => {
            const isMatch = card.dataset.preset === presetKey;
            card.classList.toggle("active", isMatch);
            const badge = card.querySelector(".preset-badge");
            if (badge) badge.textContent = isMatch ? "Active" : "Select";
        });

        const terms = config.terminology || {};
        setVal("termLeads", terms.leads || "Leads");
        setVal("termPipeline", terms.pipeline || "Sales Pipeline");
        setVal("termContacts", terms.contacts || "Contacts");
        setVal("termCompanies", terms.companies || "Companies");
        setVal("termDeals", terms.deals || "Deals");
    }

    function selectIndustryPreset(presetKey) {
        if (!window.universalCrm || !window.universalCrm.presets[presetKey]) return;
        const preset = window.universalCrm.presets[presetKey];

        document.querySelectorAll(".preset-card").forEach(card => {
            const isMatch = card.dataset.preset === presetKey;
            card.classList.toggle("active", isMatch);
            const badge = card.querySelector(".preset-badge");
            if (badge) badge.textContent = isMatch ? "Active" : "Select";
        });

        const terms = preset.terminology;
        setVal("termLeads", terms.leads);
        setVal("termPipeline", terms.pipeline);
        setVal("termContacts", terms.contacts);
        setVal("termCompanies", terms.companies);
        setVal("termDeals", terms.deals);

        showToast(`Selected '${preset.name}' industry template. Click Save to apply.`, "info");
    }

    function saveUniversalCrmSettings() {
        if (!window.universalCrm) return;
        const activeCard = document.querySelector(".preset-card.active");
        const presetKey = activeCard ? activeCard.dataset.preset : 'standard';

        const config = {
            preset: presetKey,
            terminology: {
                leads: getVal("termLeads").trim() || "Leads",
                pipeline: getVal("termPipeline").trim() || "Sales Pipeline",
                contacts: getVal("termContacts").trim() || "Contacts",
                companies: getVal("termCompanies").trim() || "Companies",
                deals: getVal("termDeals").trim() || "Deals"
            },
            customFields: window.universalCrm.getConfig().customFields
        };

        window.universalCrm.saveConfig(config);
        showToast("Universal CRM terminology & preset saved successfully!", "success");
    }

    // Public API Window Binding
    window.settingsApp = {
        saveAllSettings,
        resetDemoChanges,
        switchSection,
        updateStage,
        deleteStage,
        deleteCustomField,
        exportCSV,
        exportJSONBackup,
        clearLocalDemoData: resetAllSettings,
        resetAllSettings,
        deleteWorkspace,
        selectIndustryPreset,
        saveUniversalCrmSettings,
        loadUniversalCrmSettings,
        showToast
    };
})();

