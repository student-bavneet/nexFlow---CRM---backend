/**
 * NexFlow CRM Softphone Controller JavaScript
 * Manages softphone state, dialing, call timer, controls, call notes,
 * sessionStorage active-call persistence, and localStorage call logs.
 */

(function() {
    const STORAGE_ACTIVE_CALL = "NexFlow_active_call";
    const STORAGE_RECENT_CALLS = "NexFlow_recent_calls";

    // Softphone State Variables
    let currentState = "idle"; // idle, calling, ringing, connected, on_hold, ended
    let currentCallData = null;
    let callStartTime = null;
    let callTimerInterval = null;
    let isMuted = false;
    let isOnHold = false;
    let isSpeaker = false;
    let isKeypadActive = false;
    let isMinimized = false;
    let incomingCallData = null;

    // Default Contacts Fallback
    const DEFAULT_CONTACTS = [
        { id: "L-001", name: "Marcus Thompson", company: "Acme Corp", phone: "+1 (415) 555-0123", email: "marcus@acmecorp.io", color: "#7C3AED", initials: "MT" },
        { id: "L-002", name: "Priya Sharma", company: "Novatel Systems", phone: "+1 (650) 555-0189", email: "priya@novatel.com", color: "#0284C7", initials: "PS" },
        { id: "L-003", name: "Daniel Reyes", company: "BrightPath Analytics", phone: "+1 (512) 555-0247", email: "d.reyes@brightpath.co", color: "#059669", initials: "DR" },
        { id: "L-004", name: "Sophie Laurent", company: "Meridian Group", phone: "+33 1 55 00 12 34", email: "slaurent@meridian.eu", color: "#7C3AED", initials: "SL" },
        { id: "L-005", name: "Kwame Asante", company: "Vertex Technologies", phone: "+1 (312) 555-0334", email: "kasante@vertextech.io", color: "#0284C7", initials: "KA" },
        { id: "L-006", name: "Elena Petrova", company: "CloudShift Inc.", phone: "+1 (206) 555-0442", email: "elena@cloudshift.com", color: "#059669", initials: "EP" },
        { id: "L-007", name: "Amir Hassan", company: "Fortis Group", phone: "+971 4 555 0133", email: "ahassan@fortisgroup.ae", color: "#7C3AED", initials: "AH" },
        { id: "L-008", name: "Victoria Chen", company: "Synapse AI", phone: "+1 (415) 555-0598", email: "vchen@synapse-ai.io", color: "#0284C7", initials: "VC" }
    ];

    document.addEventListener("DOMContentLoaded", function() {
        initSoftphoneUI();
        initLocalStorageCallLogs();
        setupKeyboardDialing();
        restoreActiveCallFromSession();
    });

    // Public Softphone API
    window.softphone = {
        open: openSoftphoneDrawer,
        close: closeSoftphoneDrawer,
        dialNumber: function(number, contactObj) {
            openSoftphoneDrawer();
            const phoneInput = document.getElementById("phoneDisplayInput");
            if (phoneInput) phoneInput.value = number;
            if (contactObj) {
                currentCallData = contactObj;
            }
            updateSoftphoneSelectedContactCard();
            switchSoftphoneTab("dialer");
        },
        dialLead: function(leadId) {
            const contacts = getContactsList();
            const contact = contacts.find(c => c.id === leadId);
            if (contact) {
                window.softphone.dialNumber(contact.phone, contact);
            } else {
                openSoftphoneDrawer();
            }
        }
    };

    function updateSoftphoneSelectedContactCard() {
        const card = document.getElementById("softphoneSelectedContactCard");
        if (!card) return;

        if (currentCallData) {
            card.style.display = "block";
            const avatar = document.getElementById("spSelectedAvatar");
            const name = document.getElementById("spSelectedName");
            const meta = document.getElementById("spSelectedMeta");
            const phone = document.getElementById("spSelectedPhone");
            const badge = document.getElementById("spSelectedBadge");

            if (avatar) {
                avatar.textContent = currentCallData.initials || "C";
                avatar.style.backgroundColor = currentCallData.color || "#7C3AED";
            }
            if (name) name.textContent = currentCallData.name;
            if (meta) meta.textContent = "VP of Operations • " + (currentCallData.company || "Company");
            if (phone) phone.textContent = currentCallData.phone;
            if (badge) badge.textContent = currentCallData.status || "Qualified";
        } else {
            card.style.display = "none";
        }
    }

    window.openLeadDrawerFromSoftphone = function() {
        if (currentCallData && currentCallData.id && typeof window.openLeadDrawer === "function") {
            closeSoftphoneDrawer();
            window.openLeadDrawer(currentCallData.id);
        }
    };

    function getContactsList() {
        if (window.LEADS_MOCK_DATA && Array.isArray(window.LEADS_MOCK_DATA) && window.LEADS_MOCK_DATA.length > 0) {
            return window.LEADS_MOCK_DATA.map(l => ({
                id: l.id,
                name: l.name,
                company: l.company,
                phone: l.phone,
                email: l.email,
                color: l.assigneeColor || "#7C3AED",
                initials: l.name.split(" ").map(n => n[0]).join("").toUpperCase()
            }));
        }
        return DEFAULT_CONTACTS;
    }

    function initSoftphoneUI() {
        const topbarPhoneBtn = document.getElementById("topbarPhoneBtn");
        const overlay = document.getElementById("softphoneOverlay");
        const closeBtn = document.getElementById("softphoneCloseBtn");
        const minimizeBtn = document.getElementById("softphoneMinimizeBtn");
        const searchInput = document.getElementById("softphoneSearchInput");
        const statusSelect = document.getElementById("agentStatusSelect");
        const statusDot = document.getElementById("agentStatusDot");
        const followupCheckbox = document.getElementById("callFollowupCheckbox");
        const notesInput = document.getElementById("callNotesInput");
        const outcomeSelect = document.getElementById("callOutcomeSelect");

        if (topbarPhoneBtn) {
            topbarPhoneBtn.addEventListener("click", function(e) {
                e.stopPropagation();
                if (overlay && overlay.classList.contains("show")) {
                    closeSoftphoneDrawer();
                } else {
                    openSoftphoneDrawer();
                }
            });
        }

        if (closeBtn) closeBtn.addEventListener("click", closeSoftphoneDrawer);
        if (minimizeBtn) minimizeBtn.addEventListener("click", minimizeSoftphone);

        if (overlay) {
            overlay.addEventListener("click", function(e) {
                if (e.target === overlay) closeSoftphoneDrawer();
            });
        }

        if (statusSelect && statusDot) {
            statusSelect.addEventListener("change", function() {
                statusDot.className = "agent-status-dot " + this.value;
            });
        }

        if (searchInput) {
            searchInput.addEventListener("input", function() {
                handleContactSearch(this.value);
            });
            searchInput.addEventListener("focus", function() {
                handleContactSearch(this.value);
            });
        }

        if (notesInput) {
            notesInput.addEventListener("input", function() {
                saveActiveCallToSession();
            });
        }

        if (outcomeSelect) {
            outcomeSelect.addEventListener("change", function() {
                saveActiveCallToSession();
            });
        }

        document.addEventListener("click", function(e) {
            const dropdown = document.getElementById("searchResultsDropdown");
            if (dropdown && !e.target.closest(".softphone-search-box")) {
                dropdown.classList.remove("show");
            }
        });

        if (followupCheckbox) {
            followupCheckbox.addEventListener("change", function() {
                const group = document.getElementById("followupDateGroup");
                if (group) group.style.display = this.checked ? "block" : "none";
                saveActiveCallToSession();
            });
        }

        renderContactsDirectory();
    }

    function openSoftphoneDrawer() {
        const overlay = document.getElementById("softphoneOverlay");
        const floatBar = document.getElementById("floatingCallBar");
        if (overlay) {
            overlay.classList.add("show");
            isMinimized = false;
            if (floatBar) floatBar.classList.remove("show");
            document.body.style.overflow = (window.innerWidth <= 768) ? "hidden" : "";
            saveActiveCallToSession();
        }
    }

    function closeSoftphoneDrawer() {
        const overlay = document.getElementById("softphoneOverlay");
        if (overlay) {
            overlay.classList.remove("show");
            document.body.style.overflow = "";
        }
        if (isActiveCallState()) {
            minimizeSoftphone();
        }
        const topbarPhoneBtn = document.getElementById("topbarPhoneBtn");
        if (topbarPhoneBtn) topbarPhoneBtn.focus();
    }

    window.minimizeSoftphone = function() {
        const overlay = document.getElementById("softphoneOverlay");
        if (overlay) overlay.classList.remove("show");
        document.body.style.overflow = "";
        isMinimized = true;

        if (isActiveCallState()) {
            updateFloatingCallWidgetUI();
        }
        saveActiveCallToSession();
    };

    window.restoreSoftphonePanel = function() {
        openSoftphoneDrawer();
    };

    window.switchSoftphoneTab = function(tabName) {
        document.querySelectorAll(".softphone-tab").forEach(t => t.classList.remove("active"));
        document.querySelectorAll(".softphone-view").forEach(v => v.classList.remove("active"));

        const tabBtn = document.getElementById("tab" + tabName.charAt(0).toUpperCase() + tabName.slice(1));
        const viewEl = (tabName === "activeCall") ? document.getElementById("viewActiveCall")
                     : (tabName === "recent") ? document.getElementById("viewRecent")
                     : (tabName === "contacts") ? document.getElementById("viewContacts")
                     : document.getElementById("viewDialer");

        if (tabBtn) tabBtn.classList.add("active");
        if (viewEl) viewEl.classList.add("active");

        if (tabName === "recent") renderRecentCallsList();
        if (tabName === "contacts") renderContactsDirectory();
    };

    // Live Contact Search
    function handleContactSearch(query) {
        const dropdown = document.getElementById("searchResultsDropdown");
        if (!dropdown) return;

        query = query.toLowerCase().trim();
        if (!query) {
            dropdown.classList.remove("show");
            return;
        }

        const contacts = getContactsList();
        const matches = contacts.filter(c => 
            c.name.toLowerCase().includes(query) ||
            c.company.toLowerCase().includes(query) ||
            c.email.toLowerCase().includes(query) ||
            c.phone.includes(query)
        );

        if (matches.length === 0) {
            dropdown.innerHTML = '<div style="padding: 10px; font-size: 12px; color: var(--text-muted); text-align: center;">No matching contacts</div>';
        } else {
            dropdown.innerHTML = matches.map(c => `
                <div class="search-result-item" onclick="selectSearchResult('${c.id}')">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div class="avatar avatar-xs" style="background-color: ${c.color};">${c.initials}</div>
                        <div>
                            <div style="font-size: 13px; font-weight: 600; color: var(--text-heading);">${c.name}</div>
                            <div style="font-size: 11px; color: var(--text-muted);">${c.company} • ${c.phone}</div>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-xs" style="background-color: #12B76A; border: none; padding: 2px 8px;">Call</button>
                </div>
            `).join("");
        }

        dropdown.classList.add("show");
    }

    window.selectSearchResult = function(contactId) {
        const contacts = getContactsList();
        const contact = contacts.find(c => c.id === contactId);
        const dropdown = document.getElementById("searchResultsDropdown");
        const phoneInput = document.getElementById("phoneDisplayInput");
        const searchInput = document.getElementById("softphoneSearchInput");

        if (dropdown) dropdown.classList.remove("show");
        if (searchInput) searchInput.value = "";

        if (contact) {
            currentCallData = contact;
            if (phoneInput) phoneInput.value = contact.phone;
            initiateSoftphoneCall();
        }
    };

    // Dialpad Functions
    window.pressDialKey = function(key) {
        const phoneInput = document.getElementById("phoneDisplayInput");
        if (phoneInput) {
            phoneInput.value += key;
        }
    };

    window.backspacePhoneInput = function() {
        const phoneInput = document.getElementById("phoneDisplayInput");
        if (phoneInput && phoneInput.value.length > 0) {
            phoneInput.value = phoneInput.value.slice(0, -1);
        }
    };

    function setupKeyboardDialing() {
        document.addEventListener("keydown", function(e) {
            const overlay = document.getElementById("softphoneOverlay");
            if (!overlay || !overlay.classList.contains("show")) return;

            if (document.activeElement && (document.activeElement.tagName === "TEXTAREA" || document.activeElement.id === "softphoneSearchInput")) {
                return;
            }

            if (/^[0-9*+#]$/.test(e.key)) {
                pressDialKey(e.key);
            } else if (e.key === "Backspace") {
                backspacePhoneInput();
            } else if (e.key === "Enter" && currentState === "idle") {
                initiateSoftphoneCall();
            } else if (e.key === "Escape") {
                closeSoftphoneDrawer();
            }
        });
    }

    // Call Progression & Timer Logic
    window.initiateSoftphoneCall = function() {
        const phoneInput = document.getElementById("phoneDisplayInput");
        const countryCode = document.getElementById("countryCodeSelect") ? document.getElementById("countryCodeSelect").value : "+1";
        let rawNumber = phoneInput ? phoneInput.value.trim() : "";

        if (!rawNumber && !currentCallData) {
            showAlertModal({ title: "Phone Required", message: "Please enter a phone number or select a contact.", type: "warning" });
            return;
        }

        if (!currentCallData) {
            const fullPhone = rawNumber.startsWith("+") ? rawNumber : (countryCode + " " + rawNumber);
            currentCallData = {
                id: "C-" + Math.floor(Math.random() * 900 + 100),
                name: fullPhone,
                company: "Direct Dial",
                phone: fullPhone,
                email: "N/A",
                color: "#2563EB",
                initials: "#"
            };
        }

        // Transition 1: Ready -> Calling
        currentState = "calling";
        callStartTime = Date.now();
        isMuted = false;
        isOnHold = false;
        isSpeaker = false;

        renderCallActiveScreen();
        switchSoftphoneTab("activeCall");
        updateCallStatusTag("Calling...", "calling");
        saveActiveCallToSession();

        // Transition 2: Calling -> Ringing (after 1.2s)
        setTimeout(() => {
            if (currentState === "calling") {
                currentState = "ringing";
                updateCallStatusTag("Ringing...", "calling");
                saveActiveCallToSession();
            }
        }, 1200);

        // Transition 3: Ringing -> Connected (after 2.7s total)
        setTimeout(() => {
            if (currentState === "ringing") {
                currentState = "connected";
                updateCallStatusTag("Connected • Demo HD Voice", "connected");
                startContinuousCallTimer();
                const topbarBtn = document.getElementById("topbarPhoneBtn");
                if (topbarBtn) topbarBtn.classList.add("active-call");
                saveActiveCallToSession();
            }
        }, 2700);
    };

    function startContinuousCallTimer() {
        clearInterval(callTimerInterval);
        callTimerInterval = setInterval(() => {
            if (isActiveCallState()) {
                updateCallTimerDisplay();
            }
        }, 1000);
        updateCallTimerDisplay();
    }

    function stopContinuousCallTimer() {
        clearInterval(callTimerInterval);
    }

    function getElapsedSeconds() {
        if (!callStartTime) return 0;
        return Math.max(0, Math.floor((Date.now() - callStartTime) / 1000));
    }

    function formatTimeDisplay(secondsCount) {
        const mins = String(Math.floor(secondsCount / 60)).padStart(2, '0');
        const secs = String(secondsCount % 60).padStart(2, '0');
        return `${mins}:${secs}`;
    }

    function updateCallTimerDisplay() {
        const elapsed = getElapsedSeconds();
        const formatted = formatTimeDisplay(elapsed);

        const timerEl = document.getElementById("activeCallTimer");
        if (timerEl) timerEl.textContent = formatted;

        const floatTimer = document.getElementById("floatTimer");
        if (floatTimer) floatTimer.textContent = formatted;
    }

    function updateCallStatusTag(text, stateClass) {
        const tag = document.getElementById("activeCallStatusTag");
        if (tag) {
            tag.textContent = text;
            tag.className = "call-status-tag " + stateClass;
        }
    }

    function renderCallActiveScreen() {
        if (!currentCallData) return;

        const avatar = document.getElementById("activeCallAvatar");
        const name = document.getElementById("activeCallName");
        const company = document.getElementById("activeCallCompany");
        const phone = document.getElementById("activeCallPhone");

        if (avatar) {
            avatar.textContent = currentCallData.initials || "C";
            avatar.style.backgroundColor = currentCallData.color || "#7C3AED";
        }
        if (name) name.textContent = currentCallData.name;
        if (company) company.textContent = currentCallData.company;
        if (phone) phone.textContent = currentCallData.phone;

        updateCallTimerDisplay();
        updateInCallControlsUI();
    }

    function updateInCallControlsUI() {
        const muteBtn = document.getElementById("ctrlMute");
        const holdBtn = document.getElementById("ctrlHold");
        const speakerBtn = document.getElementById("ctrlSpeaker");
        const keypadBtn = document.getElementById("ctrlKeypad");

        if (muteBtn) muteBtn.classList.toggle("active", isMuted);
        if (holdBtn) holdBtn.classList.toggle("active", isOnHold);
        if (holdBtn) holdBtn.classList.toggle("hold", isOnHold);
        if (speakerBtn) speakerBtn.classList.toggle("active", isSpeaker);
        if (keypadBtn) keypadBtn.classList.toggle("active", isKeypadActive);

        const labelMute = document.getElementById("labelMute");
        if (labelMute) labelMute.textContent = isMuted ? "Unmute" : "Mute";

        const labelHold = document.getElementById("labelHold");
        if (labelHold) labelHold.textContent = isOnHold ? "Resume" : "Hold";

        const floatMuteBtn = document.getElementById("floatMuteBtn");
        if (floatMuteBtn) floatMuteBtn.classList.toggle("active", isMuted);
    }

    window.toggleCallMute = function() {
        isMuted = !isMuted;
        updateInCallControlsUI();
        saveActiveCallToSession();
        showSoftphoneToast(isMuted ? "Microphone Muted (Demo)" : "Microphone Active");
    };

    window.toggleCallHold = function() {
        if (!isActiveCallState()) return;

        isOnHold = !isOnHold;
        if (isOnHold) {
            currentState = "on_hold";
            updateCallStatusTag("On Hold (Demo)", "on-hold");
            showSoftphoneToast("Call Placed on Hold");
        } else {
            currentState = "connected";
            updateCallStatusTag("Connected • Demo HD Voice", "connected");
            showSoftphoneToast("Call Resumed");
        }

        updateInCallControlsUI();
        updateFloatingCallWidgetUI();
        saveActiveCallToSession();
    };

    window.toggleCallSpeaker = function() {
        isSpeaker = !isSpeaker;
        updateInCallControlsUI();
        saveActiveCallToSession();
        showSoftphoneToast(isSpeaker ? "Speaker Output Enabled" : "Earpiece Output");
    };

    window.toggleCallKeypad = function() {
        isKeypadActive = !isKeypadActive;
        updateInCallControlsUI();
        showSoftphoneToast(isKeypadActive ? "In-Call Keypad Active" : "Keypad Hidden");
    };

    window.triggerCallAddParticipant = function() {
        showSoftphoneToast("Demo: Participant invite sent");
    };

    window.triggerCallTransfer = function() {
        showSoftphoneToast("Demo: Call transfer initiated to Sales Team");
    };

    window.endSoftphoneCall = function() {
        stopContinuousCallTimer();
        currentState = "ended";
        updateCallStatusTag("Call Ended", "calling");

        const topbarBtn = document.getElementById("topbarPhoneBtn");
        if (topbarBtn) topbarBtn.classList.remove("active-call");

        const floatBar = document.getElementById("floatingCallBar");
        if (floatBar) floatBar.classList.remove("show");

        // Record recent call log to localStorage
        if (currentCallData) {
            const elapsed = getElapsedSeconds();
            const formatted = formatTimeDisplay(elapsed);
            const notesInput = document.getElementById("callNotesInput");
            const outcomeSelect = document.getElementById("callOutcomeSelect");

            const logs = JSON.parse(localStorage.getItem(STORAGE_RECENT_CALLS) || "[]");
            logs.unshift({
                id: String(Date.now()),
                name: currentCallData.name,
                company: currentCallData.company,
                phone: currentCallData.phone,
                dir: "outgoing",
                outcome: outcomeSelect ? outcomeSelect.value : "Connected",
                date: "Just now",
                duration: formatted,
                notes: notesInput ? notesInput.value.trim() : "",
                color: currentCallData.color || "#7C3AED",
                initials: currentCallData.initials || "C"
            });
            localStorage.setItem(STORAGE_RECENT_CALLS, JSON.stringify(logs));
        }

        sessionStorage.removeItem(STORAGE_ACTIVE_CALL);
        showSoftphoneToast("Call ended. Activity saved to recent calls.");

        setTimeout(() => {
            currentState = "idle";
            currentCallData = null;
            callStartTime = null;
            if (!isMinimized) {
                switchSoftphoneTab("dialer");
            }
        }, 1200);
    };

    function isActiveCallState() {
        return (currentState === "calling" || currentState === "ringing" || currentState === "connected" || currentState === "on_hold");
    }

    function updateFloatingCallWidgetUI() {
        const floatBar = document.getElementById("floatingCallBar");
        if (!floatBar) return;

        if (isActiveCallState()) {
            floatBar.classList.add("show");

            const avatar = document.getElementById("floatAvatar");
            const name = document.getElementById("floatName");
            const statusText = document.getElementById("floatStatusText");
            const statusDot = document.getElementById("floatStatusDot");

            if (avatar && currentCallData) {
                avatar.textContent = currentCallData.initials || "C";
                avatar.style.backgroundColor = currentCallData.color || "#7C3AED";
            }

            if (name && currentCallData) {
                name.textContent = currentCallData.name;
            }

            if (statusText) {
                statusText.textContent = (currentState === "on_hold") ? "On Hold" : "On Call";
            }

            if (statusDot) {
                statusDot.className = (currentState === "on_hold") ? "floating-status-dot on-hold" : "floating-status-dot";
            }

            updateCallTimerDisplay();
        } else {
            floatBar.classList.remove("show");
        }
    }

    // SessionStorage Active Call Persistence across Page Navigations
    function saveActiveCallToSession() {
        if (!isActiveCallState() || !currentCallData) {
            sessionStorage.removeItem(STORAGE_ACTIVE_CALL);
            return;
        }

        const notesInput = document.getElementById("callNotesInput");
        const outcomeSelect = document.getElementById("callOutcomeSelect");
        const followupCb = document.getElementById("callFollowupCheckbox");
        const followupDate = document.getElementById("followupDateInput");

        const sessionState = {
            currentCallData: currentCallData,
            currentState: currentState,
            callStartTime: callStartTime,
            isMuted: isMuted,
            isOnHold: isOnHold,
            isSpeaker: isSpeaker,
            isMinimized: isMinimized,
            draftNotes: notesInput ? notesInput.value : "",
            outcome: outcomeSelect ? outcomeSelect.value : "Connected",
            followup: followupCb ? followupCb.checked : false,
            followupDate: followupDate ? followupDate.value : ""
        };

        sessionStorage.setItem(STORAGE_ACTIVE_CALL, JSON.stringify(sessionState));
    }

    function restoreActiveCallFromSession() {
        const raw = sessionStorage.getItem(STORAGE_ACTIVE_CALL);
        if (!raw) return;

        try {
            const data = JSON.parse(raw);
            if (data && data.currentCallData && data.callStartTime && data.currentState !== "ended") {
                currentCallData = data.currentCallData;
                currentState = data.currentState;
                callStartTime = data.callStartTime;
                isMuted = !!data.isMuted;
                isOnHold = !!data.isOnHold;
                isSpeaker = !!data.isSpeaker;
                isMinimized = !!data.isMinimized;

                // Restore notes & form controls
                const notesInput = document.getElementById("callNotesInput");
                const outcomeSelect = document.getElementById("callOutcomeSelect");
                const followupCb = document.getElementById("callFollowupCheckbox");
                const followupDate = document.getElementById("followupDateInput");

                if (notesInput && data.draftNotes) notesInput.value = data.draftNotes;
                if (outcomeSelect && data.outcome) outcomeSelect.value = data.outcome;
                if (followupCb && data.followup !== undefined) {
                    followupCb.checked = data.followup;
                    const group = document.getElementById("followupDateGroup");
                    if (group) group.style.display = data.followup ? "block" : "none";
                }
                if (followupDate && data.followupDate) followupDate.value = data.followupDate;

                // Restore active call view
                renderCallActiveScreen();
                startContinuousCallTimer();

                const topbarBtn = document.getElementById("topbarPhoneBtn");
                if (topbarBtn) topbarBtn.classList.add("active-call");

                if (currentState === "on_hold") {
                    updateCallStatusTag("On Hold (Demo)", "on-hold");
                } else if (currentState === "connected") {
                    updateCallStatusTag("Connected • Demo HD Voice", "connected");
                } else {
                    updateCallStatusTag("Calling...", "calling");
                }

                if (isMinimized) {
                    minimizeSoftphone();
                } else {
                    openSoftphoneDrawer();
                    switchSoftphoneTab("activeCall");
                }

                updateFloatingCallWidgetUI();
            }
        } catch (err) {
            console.error("Error restoring softphone session:", err);
            sessionStorage.removeItem(STORAGE_ACTIVE_CALL);
        }
    }

    // Call Logging & Memory Persistence (localStorage)
    function initLocalStorageCallLogs() {
        if (!localStorage.getItem(STORAGE_RECENT_CALLS)) {
            const initialLogs = [
                { id: "1", name: "Marcus Thompson", company: "Acme Corp", phone: "+1 (415) 555-0123", dir: "outgoing", outcome: "Connected", date: "Today 10:15 AM", duration: "02:45", color: "#7C3AED", initials: "MT" },
                { id: "2", name: "Priya Sharma", company: "Novatel Systems", phone: "+1 (650) 555-0189", dir: "incoming", outcome: "Connected", date: "Yesterday 3:40 PM", duration: "04:12", color: "#0284C7", initials: "PS" },
                { id: "3", name: "Daniel Reyes", company: "BrightPath Analytics", phone: "+1 (512) 555-0247", dir: "missed", outcome: "No Answer", date: "Yesterday 11:05 AM", duration: "00:00", color: "#059669", initials: "DR" }
            ];
            localStorage.setItem(STORAGE_RECENT_CALLS, JSON.stringify(initialLogs));
        }
    }

    window.saveCallActivityLog = function() {
        if (!currentCallData) {
            showAlertModal({ title: "Call Data Required", message: "No active call data to save.", type: "warning" });
            return;
        }

        const outcomeSelect = document.getElementById("callOutcomeSelect");
        const notesInput = document.getElementById("callNotesInput");
        const followupCb = document.getElementById("callFollowupCheckbox");

        const outcome = outcomeSelect ? outcomeSelect.value : "Connected";
        const notes = notesInput ? notesInput.value.trim() : "";
        const elapsed = getElapsedSeconds();
        const durationFormatted = formatTimeDisplay(elapsed);

        const newLog = {
            id: String(Date.now()),
            name: currentCallData.name,
            company: currentCallData.company,
            phone: currentCallData.phone,
            dir: "outgoing",
            outcome: outcome,
            date: "Just now",
            duration: durationFormatted,
            notes: notes,
            followup: followupCb ? followupCb.checked : false,
            color: currentCallData.color || "#7C3AED",
            initials: currentCallData.initials || "C"
        };

        const logs = JSON.parse(localStorage.getItem(STORAGE_RECENT_CALLS) || "[]");
        logs.unshift(newLog);
        localStorage.setItem(STORAGE_RECENT_CALLS, JSON.stringify(logs));

        showSoftphoneToast("Call activity saved to log!");

        if (notesInput) notesInput.value = "";
        if (followupCb) followupCb.checked = false;

        endSoftphoneCall();
        switchSoftphoneTab("recent");
    };

    let currentRecentFilter = "all";
    let currentRecentQuery = "";

    window.filterRecentCalls = function(filter, elem) {
        currentRecentFilter = filter;
        document.querySelectorAll(".softphone-filter-chip").forEach(c => c.classList.remove("active"));
        if (elem) {
            elem.classList.add("active");
        } else {
            const chip = Array.from(document.querySelectorAll(".softphone-filter-chip")).find(c => c.textContent.toLowerCase() === filter);
            if (chip) chip.classList.add("active");
        }
        renderRecentCallsList(currentRecentFilter, currentRecentQuery);
    };

    window.onRecentCallsSearch = function(query) {
        currentRecentQuery = query;
        renderRecentCallsList(currentRecentFilter, currentRecentQuery);
    };

    function renderRecentCallsList(filter = "all", query = "") {
        const container = document.getElementById("recentCallsContainer");
        if (!container) return;

        const logs = JSON.parse(localStorage.getItem(STORAGE_RECENT_CALLS) || "[]");
        const filtered = logs.filter(l => {
            const matchesFilter = (filter === "all" || l.dir === filter);
            const q = query.toLowerCase();
            const matchesQuery = !query || ((l.name && l.name.toLowerCase().includes(q)) || (l.phone && l.phone.includes(q)) || (l.company && l.company.toLowerCase().includes(q)));
            return matchesFilter && matchesQuery;
        });

        if (filtered.length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 24px 16px; font-size: 13px; color: var(--text-muted);">No call history matches your filter.</div>';
            return;
        }

        container.innerHTML = filtered.map(item => {
            const displayName = item.name || item.phone || "Unknown Number";
            const displayMeta = item.company ? `${item.company} • ${item.date} • ${item.duration}` : `Direct Dial • ${item.date} • ${item.duration}`;
            const dirIcon = item.dir === 'incoming' ? '↙' : item.dir === 'outgoing' ? '↗' : '✕';

            return `
                <div class="softphone-recent-item">
                    <div class="softphone-recent-left">
                        <div class="softphone-recent-dir-icon ${item.dir}">
                            ${dirIcon}
                        </div>
                        <div class="softphone-recent-details">
                            <div class="softphone-recent-name">${displayName}</div>
                            <div class="softphone-recent-meta">${displayMeta}</div>
                        </div>
                    </div>
                    <div class="softphone-recent-right">
                        <span class="softphone-outcome-badge ${item.dir}">${item.outcome}</span>
                        <button class="softphone-callback-btn" title="Call Back" onclick="window.softphone.dialNumber('${item.phone}')">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                        </button>
                    </div>
                </div>
            `;
        }).join("");
    }

    window.renderContactsDirectory = function(searchQuery = "") {
        const container = document.getElementById("contactsDirectoryContainer");
        if (!container) return;

        let contacts = getContactsList();
        if (searchQuery) {
            const q = searchQuery.toLowerCase();
            contacts = contacts.filter(c => c.name.toLowerCase().includes(q) || c.company.toLowerCase().includes(q) || c.phone.includes(q));
        }

        if (contacts.length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 24px 16px; font-size: 13px; color: var(--text-muted);">No contacts found matching your search.</div>';
            return;
        }

        container.innerHTML = contacts.map(c => `
            <div class="softphone-contact-item" onclick="window.softphone.dialNumber('${c.phone}', { id: '${c.id}', name: '${c.name}', company: '${c.company}', phone: '${c.phone}', color: '${c.color}', initials: '${c.initials}' })">
                <div class="avatar avatar-sm" style="background-color: ${c.color}; flex-shrink: 0;">${c.initials}</div>
                <div class="softphone-contact-details">
                    <div class="softphone-contact-name">${c.name}</div>
                    <div class="softphone-contact-meta">${c.company} • ${c.phone}</div>
                </div>
                <button class="softphone-contact-call-btn" title="Call Contact" onclick="event.stopPropagation(); window.softphone.dialNumber('${c.phone}', { id: '${c.id}', name: '${c.name}', company: '${c.company}', phone: '${c.phone}', color: '${c.color}', initials: '${c.initials}' })">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                </button>
            </div>
        `).join("");
    };

    // Incoming Call Demo Simulation (ONLY triggered by user click)
    window.simulateIncomingCallDemo = function() {
        const contacts = getContactsList();
        const rand = contacts[Math.floor(Math.random() * contacts.length)];
        incomingCallData = rand;

        const modal = document.getElementById("incomingCallModal");
        const avatar = document.getElementById("incAvatar");
        const name = document.getElementById("incName");
        const company = document.getElementById("incCompany");
        const phone = document.getElementById("incPhone");

        if (avatar) {
            avatar.textContent = rand.initials;
            avatar.style.backgroundColor = rand.color;
        }
        if (name) name.textContent = rand.name;
        if (company) company.textContent = rand.company;
        if (phone) phone.textContent = rand.phone;

        if (modal) {
            modal.style.setProperty("display", "flex", "important");
            modal.classList.add("show");
        }
    };

    window.acceptIncomingCall = function() {
        const modal = document.getElementById("incomingCallModal");
        if (modal) {
            modal.classList.remove("show");
            modal.style.setProperty("display", "none", "important");
        }

        if (incomingCallData) {
            currentCallData = incomingCallData;
            callStartTime = Date.now();
            openSoftphoneDrawer();
            currentState = "connected";
            renderCallActiveScreen();
            switchSoftphoneTab("activeCall");
            updateCallStatusTag("Connected • Incoming Call", "connected");
            startContinuousCallTimer();
            saveActiveCallToSession();
        }
    };

    window.declineIncomingCall = function() {
        const modal = document.getElementById("incomingCallModal");
        if (modal) {
            modal.classList.remove("show");
            modal.style.setProperty("display", "none", "important");
        }

        if (incomingCallData) {
            const logs = JSON.parse(localStorage.getItem(STORAGE_RECENT_CALLS) || "[]");
            logs.unshift({
                id: String(Date.now()),
                name: incomingCallData.name,
                company: incomingCallData.company,
                phone: incomingCallData.phone,
                dir: "missed",
                outcome: "Missed Call",
                date: "Just now",
                duration: "00:00",
                color: incomingCallData.color,
                initials: incomingCallData.initials
            });
            localStorage.setItem(STORAGE_RECENT_CALLS, JSON.stringify(logs));
            showSoftphoneToast("Incoming call declined (Logged as Missed)");
            renderRecentCallsList(currentRecentFilter, currentRecentQuery);
        }
    };

    // Helper Toast
    function showSoftphoneToast(msg) {
        const toast = document.createElement("div");
        toast.style.cssText = "position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background-color: #101828; color: #ffffff; padding: 10px 18px; border-radius: 999px; font-size: 13px; font-weight: 500; box-shadow: 0 10px 15px rgba(0,0,0,0.2); z-index: 200; pointer-events: none; transition: opacity 0.3s ease;";
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = "0";
            setTimeout(() => toast.remove(), 300);
        }, 2200);
    }
})();

