<?php
// Shared Softphone & Communication Component Partials
?>

<!-- Softphone Right Side Drawer Overlay -->
<div class="softphone-overlay" id="softphoneOverlay" role="dialog" aria-modal="true" aria-labelledby="softphoneTitle">
    <div class="softphone-panel" id="softphonePanel">
        <!-- Softphone Header -->
        <div class="softphone-header">
            <div class="softphone-title-area">
                <div class="softphone-icon-box">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="softphone-title" id="softphoneTitle">Softphone</h3>
                </div>
            </div>
            <div class="softphone-header-actions">
                <button class="btn btn-ghost btn-xs" id="softphoneMinimizeBtn" title="Minimize Softphone" style="padding: 4px;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
                <button class="btn btn-ghost btn-xs" id="softphoneCloseBtn" title="Close Softphone" style="padding: 4px;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- Agent Status Selector -->
        <div class="agent-status-bar">
            <span class="agent-status-label">Agent Availability</span>
            <div class="agent-status-wrapper">
                <span class="agent-status-dot available" id="agentStatusDot"></span>
                <select class="agent-status-select" id="agentStatusSelect" aria-label="Agent Status">
                    <option value="available" selected>Available</option>
                    <option value="busy">Busy</option>
                    <option value="away">Away</option>
                    <option value="offline">Offline</option>
                </select>
            </div>
        </div>

        <!-- Softphone Tabs -->
        <div class="softphone-tabs">
            <button class="softphone-tab active" id="tabDialer" onclick="switchSoftphoneTab('dialer')">Dialer</button>
            <button class="softphone-tab" id="tabRecent" onclick="switchSoftphoneTab('recent')">Recent Calls</button>
            <button class="softphone-tab" id="tabContacts" onclick="switchSoftphoneTab('contacts')">Contacts</button>
        </div>

        <!-- Softphone Main Body -->
        <div class="softphone-body">
            <!-- VIEW 1: DIALER SCREEN -->
            <div class="softphone-view active" id="viewDialer">
                <!-- Selected Contact Card (when a contact is loaded) -->
                <div class="selected-contact-card" id="softphoneSelectedContactCard">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="avatar avatar-md" id="spSelectedAvatar" style="background-color: #7C3AED;">MT</div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                <h4 style="font-size: 14px; font-weight: 600; color: var(--text-heading); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="spSelectedName">Marcus Thompson</h4>
                                <span class="status-badge status-qualified" id="spSelectedBadge" style="font-size: 10px; padding: 1px 6px;">Qualified</span>
                            </div>
                            <p style="font-size: 11px; color: var(--text-secondary); margin-top: 2px;" id="spSelectedMeta">Sales Lead • Acme Corp</p>
                            <p style="font-size: 11px; color: var(--primary); font-weight: 600; margin-top: 1px;" id="spSelectedPhone">+1 (415) 555-0123</p>
                        </div>
                    </div>
                </div>

                <!-- Live Contact Search Field -->
                <div class="softphone-search-box" style="margin-top: 12px;">
                    <div class="input-field-container">
                        <span class="input-icon">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </span>
                        <input type="text" id="softphoneSearchInput" class="input-control input-sm has-icon" placeholder="Search name, company or number…" autocomplete="off">
                    </div>
                    <div class="contact-search-results" id="searchResultsDropdown"></div>
                </div>

                <!-- Phone Input & Country Selector -->
                <div class="phone-input-group">
                    <select class="country-select" id="countryCodeSelect" aria-label="Country Code">
                        <option value="+1" selected>🇺🇸 +1</option>
                        <option value="+44">🇬🇧 +44</option>
                        <option value="+33">🇫🇷 +33</option>
                        <option value="+49">🇩🇪 +49</option>
                        <option value="+91">🇮🇳 +91</option>
                        <option value="+971">🇦🇪 +971</option>
                    </select>
                    <input type="text" class="phone-display-input" id="phoneDisplayInput" placeholder="Enter number..." aria-label="Phone Number">
                </div>

                <!-- Numeric Keypad -->
                <div class="dialpad-grid">
                    <button class="dialpad-btn" onclick="pressDialKey('1')"><span class="dialpad-num">1</span><span class="dialpad-sub">&nbsp;</span></button>
                    <button class="dialpad-btn" onclick="pressDialKey('2')"><span class="dialpad-num">2</span><span class="dialpad-sub">ABC</span></button>
                    <button class="dialpad-btn" onclick="pressDialKey('3')"><span class="dialpad-num">3</span><span class="dialpad-sub">DEF</span></button>
                    <button class="dialpad-btn" onclick="pressDialKey('4')"><span class="dialpad-num">4</span><span class="dialpad-sub">GHI</span></button>
                    <button class="dialpad-btn" onclick="pressDialKey('5')"><span class="dialpad-num">5</span><span class="dialpad-sub">JKL</span></button>
                    <button class="dialpad-btn" onclick="pressDialKey('6')"><span class="dialpad-num">6</span><span class="dialpad-sub">MNO</span></button>
                    <button class="dialpad-btn" onclick="pressDialKey('7')"><span class="dialpad-num">7</span><span class="dialpad-sub">PQRS</span></button>
                    <button class="dialpad-btn" onclick="pressDialKey('8')"><span class="dialpad-num">8</span><span class="dialpad-sub">TUV</span></button>
                    <button class="dialpad-btn" onclick="pressDialKey('9')"><span class="dialpad-num">9</span><span class="dialpad-sub">WXYZ</span></button>
                    <button class="dialpad-btn" onclick="pressDialKey('*')"><span class="dialpad-num">*</span><span class="dialpad-sub">&nbsp;</span></button>
                    <button class="dialpad-btn" onclick="pressDialKey('0')"><span class="dialpad-num">0</span><span class="dialpad-sub">+</span></button>
                    <button class="dialpad-btn" onclick="pressDialKey('#')"><span class="dialpad-num">#</span><span class="dialpad-sub">&nbsp;</span></button>
                </div>

                <!-- Call & Backspace Controls -->
                <div class="dialpad-actions">
                    <button class="btn btn-ghost btn-sm" onclick="backspacePhoneInput()" title="Backspace" style="padding: 8px;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 4H8l-7 8 7 8h13a2 2 0 002-2V6a2 2 0 00-2-2z"/><line x1="18" y1="9" x2="12" y2="15"/><line x1="12" y1="9" x2="18" y2="15"/></svg>
                    </button>
                    <button class="call-btn-large" id="startCallBtn" onclick="initiateSoftphoneCall()" title="Start Call">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
                        </svg>
                    </button>
                </div>

                <!-- Dev Testing Trigger -->
                <div style="margin-top: auto; padding-top: 14px; border-top: 1px solid var(--border-divider); text-align: center;">
                    <button class="btn btn-ghost btn-xs" style="color: var(--primary); font-size: 11px;" onclick="simulateIncomingCallDemo()">
                        ⚡ Dev Tool: Simulate Incoming Call
                    </button>
                </div>
            </div>

            <!-- VIEW 2: CONNECTED / ACTIVE CALL SCREEN -->
            <div class="softphone-view" id="viewActiveCall">
                <div class="call-active-container">
                    <div style="position: relative; margin-bottom: 10px;">
                        <div class="call-avatar-large" id="activeCallAvatar" style="background-color: #7C3AED; border: 3px solid #12B76A;">MT</div>
                        <span class="call-avatar-ring"></span>
                    </div>
                    <div class="call-contact-name" id="activeCallName">Marcus Thompson</div>
                    <div class="call-contact-company" id="activeCallCompany">Acme Corp</div>
                    <div class="call-contact-phone" id="activeCallPhone">+1 (415) 555-0123</div>
                    <span class="call-status-tag calling" id="activeCallStatusTag">
                        <span class="status-indicator-dot"></span>
                        <span id="activeCallStatusText">Connecting...</span>
                    </span>
                    <div class="call-timer" id="activeCallTimer">00:00</div>

                    <!-- 6 Circular In-Call Controls Grid -->
                    <div class="in-call-controls-grid">
                        <button class="in-call-btn" id="ctrlMute" onclick="toggleCallMute()" title="Mute Microphone">
                            <div class="in-call-icon-circle">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                            </div>
                            <span id="labelMute">Mute</span>
                        </button>
                        <button class="in-call-btn" id="ctrlKeypad" onclick="toggleCallKeypad()" title="In-Call Keypad">
                            <div class="in-call-icon-circle">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8" cy="8" r="1"/><circle cx="12" cy="8" r="1"/><circle cx="16" cy="8" r="1"/><circle cx="8" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="16" cy="12" r="1"/><circle cx="8" cy="16" r="1"/><circle cx="12" cy="16" r="1"/><circle cx="16" cy="16" r="1"/></svg>
                            </div>
                            <span>Keypad</span>
                        </button>
                        <button class="in-call-btn" id="ctrlSpeaker" onclick="toggleCallSpeaker()" title="Toggle Speaker">
                            <div class="in-call-icon-circle">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 010 14.14M15.54 8.46a5 5 0 010 7.07"/></svg>
                            </div>
                            <span>Speaker</span>
                        </button>
                        <button class="in-call-btn" id="ctrlHold" onclick="toggleCallHold()" title="Hold / Resume">
                            <div class="in-call-icon-circle">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                            </div>
                            <span id="labelHold">Hold</span>
                        </button>
                        <button class="in-call-btn" onclick="triggerCallAddParticipant()" title="Add Participant">
                            <div class="in-call-icon-circle">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="17" y1="11" x2="23" y2="11"/></svg>
                            </div>
                            <span>Add Person</span>
                        </button>
                        <button class="in-call-btn" onclick="triggerCallTransfer()" title="Transfer Call">
                            <div class="in-call-icon-circle">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>
                            </div>
                            <span>Transfer</span>
                        </button>
                    </div>

                    <!-- Action End Call & Minimize Row -->
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 14px;">
                        <button class="btn btn-ghost btn-xs" onclick="minimizeSoftphone()" title="Minimize Call Panel" style="color: var(--text-secondary);">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                            Minimize
                        </button>
                        <button class="end-call-btn-large" id="endCallBtn" onclick="endSoftphoneCall()" title="End Call">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M10.68 13.31a16 16 0 003.41 2.6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7 2 2 0 011.72 2v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.42 19.42 0 01-3.33-2.67m-2.67-3.34a19.79 19.79 0 01-3.07-8.63A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91"/>
                                <line x1="23" y1="1" x2="1" y2="23"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Collapsible Call Notes Section -->
                    <details class="call-notes-details" open>
                        <summary class="call-notes-summary">
                            <span>Call Log & Activity Notes</span>
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                        </summary>
                        <div class="call-notes-body">
                            <div class="form-group" style="margin-bottom: 8px;">
                                <label class="form-label">Call Outcome</label>
                                <select class="input-control input-sm" id="callOutcomeSelect">
                                    <option value="Connected" selected>Connected</option>
                                    <option value="No Answer">No Answer</option>
                                    <option value="Left Voicemail">Left Voicemail</option>
                                    <option value="Busy">Busy</option>
                                    <option value="Wrong Number">Wrong Number</option>
                                    <option value="Follow-up Required">Follow-up Required</option>
                                    <option value="Qualified">Qualified</option>
                                    <option value="Not Interested">Not Interested</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 8px;">
                                <label class="form-label">Notes</label>
                                <textarea class="input-control" id="callNotesInput" style="height: 55px; padding: 6px 8px; font-size: 12px; resize: none;" placeholder="Record discussion points..."></textarea>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                <input type="checkbox" id="callFollowupCheckbox" style="accent-color: var(--primary);">
                                <label for="callFollowupCheckbox" style="font-size: 11px; color: var(--text-body); font-weight: 500; cursor: pointer;">Schedule Follow-up</label>
                            </div>
                            <div id="followupDateGroup" style="display: none; margin-bottom: 8px;">
                                <input type="datetime-local" class="input-control input-sm" id="followupDateInput">
                            </div>
                            <button class="btn btn-primary btn-xs btn-full" onclick="saveCallActivityLog()">Save Call Activity</button>
                        </div>
                    </details>
                </div>
            </div>

            <!-- VIEW 3: RECENT CALLS LIST -->
            <div class="softphone-view" id="viewRecent">
                <div style="margin-bottom: 10px;">
                    <input type="text" class="input-control input-sm" id="softphoneRecentSearch" placeholder="Search call history…" oninput="onRecentCallsSearch(this.value)">
                </div>
                <div class="softphone-filter-chips">
                    <button class="softphone-filter-chip active" onclick="filterRecentCalls('all', this)">All</button>
                    <button class="softphone-filter-chip" onclick="filterRecentCalls('incoming', this)">Incoming</button>
                    <button class="softphone-filter-chip" onclick="filterRecentCalls('outgoing', this)">Outgoing</button>
                    <button class="softphone-filter-chip" onclick="filterRecentCalls('missed', this)">Missed</button>
                </div>
                <div class="softphone-call-list" id="recentCallsContainer">
                    <!-- Populated dynamically by JS -->
                </div>
            </div>

            <!-- VIEW 4: CONTACTS DIRECTORY -->
            <div class="softphone-view" id="viewContacts">
                <div style="margin-bottom: 12px;">
                    <input type="text" class="input-control input-sm" id="contactsTabSearch" placeholder="Search contacts…" oninput="renderContactsDirectory(this.value)">
                </div>
                <div class="softphone-contact-list" id="contactsDirectoryContainer">
                    <!-- Populated dynamically from mock data -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Active-Call Bar (Minimized Card) -->
<div class="floating-call-bar" id="floatingCallBar" onclick="restoreSoftphonePanel()">
    <div class="floating-call-info">
        <span class="floating-status-dot" id="floatStatusDot"></span>
        <div class="avatar avatar-xs" id="floatAvatar" style="background-color: #7C3AED;">MT</div>
        <div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span class="floating-call-name" id="floatName">Marcus Thompson</span>
                <span class="floating-call-status-text" id="floatStatusText">On Call</span>
            </div>
            <div class="floating-call-timer" id="floatTimer">00:00</div>
        </div>
    </div>
    <div class="floating-call-actions" onclick="event.stopPropagation()">
        <button class="floating-action-btn" id="floatMuteBtn" onclick="toggleCallMute()" title="Mute/Unmute">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
        </button>
        <button class="floating-action-btn" onclick="restoreSoftphonePanel()" title="Expand Softphone">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
        </button>
        <button class="floating-action-btn floating-end-btn" onclick="endSoftphoneCall()" title="End Call">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.68 13.31a16 16 0 003.41 2.6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7 2 2 0 011.72 2v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07"/></svg>
        </button>
    </div>
</div>

<!-- Compact Floating Incoming Call Card (Hidden by default, shown ONLY when demo triggered) -->
<div class="softphone-incoming-card" id="incomingCallModal" style="display: none !important;">
    <div class="softphone-incoming-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
        <span>Incoming Call</span>
    </div>
    <div class="softphone-incoming-user">
        <div class="avatar avatar-md" id="incAvatar" style="background-color: #0284C7; flex-shrink: 0;">PS</div>
        <div style="flex: 1; min-width: 0;">
            <div style="font-weight: 600; font-size: 14px; color: var(--text-heading);" id="incName">Priya Sharma</div>
            <div style="font-size: 12px; color: var(--text-muted);" id="incCompany">Novatel Systems</div>
            <div style="font-size: 11px; color: var(--primary); font-weight: 600;" id="incPhone">+1 (650) 555-0189</div>
        </div>
    </div>
    <div class="softphone-incoming-actions">
        <button class="softphone-accept-btn" onclick="acceptIncomingCall()">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
            Accept
        </button>
        <button class="softphone-decline-btn" onclick="declineIncomingCall()">Decline</button>
    </div>
</div>

<!-- Modal 1: Call Choice Modal -->
<div class="modal-overlay" id="callChoiceModal" role="dialog" aria-modal="true" aria-labelledby="callChoiceTitle">
    <div class="modal-container" style="max-width: 560px;background-color:#ffffff;border-radius:12px">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="callChoiceTitle" style="margin:0;">Call Contact</h3>
                <p style="margin:4px 0 0; font-size:13px; color:var(--text-muted);">Choose how you want to place this call</p>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeCallChoiceModal()" aria-label="Close modal">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body" style="padding:24px;">
            <div id="callChoiceNotice" style="display: none; padding: 10px; border-radius: var(--radius-md); background-color: #FEF2F2; color: #F04438; font-size: 12px; font-weight: 500; margin-bottom: 12px; text-align: center;">
                No valid phone number is available for this lead.
            </div>

            <div style="display:flex; align-items:center; gap:12px; margin-bottom:24px; padding-bottom:20px; border-bottom:1px solid var(--border-card);">
                <div class="team-avatar" id="callChoiceAvatar" style="width:48px; height:48px; border-radius:50%; background-color:var(--primary); color:white; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:600;">
                    MT
                </div>
                <div>
                    <div style="font-weight:600; font-size:15px; color:var(--text-heading);" id="callChoiceName">Marcus Thompson</div>
                    <div style="font-size:13px; color:var(--text-secondary); margin-top:2px;" id="callChoiceSub">Acme Corp • +1 (415) 555-0123</div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px;" id="callChoiceButtons">
                <!-- Option 1: CRM Softphone -->
                <div class="call-method-card" id="btnCallSoftphone" style="display:flex; align-items:center; gap:16px; padding:16px; border:1px solid var(--border-card); border-radius:10px; cursor:pointer; transition:all 0.2s; position:relative;"
                     onmouseover="this.style.borderColor='var(--primary)'; this.style.backgroundColor='#F8FAFC';" 
                     onmouseout="this.style.borderColor='var(--border-card)'; this.style.backgroundColor='transparent';"
                     onclick="executeCallSoftphone()">
                    <div style="width:40px; height:40px; border-radius:8px; background-color:#EFF6FF; color:var(--primary); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </div>
                    <div style="flex:1;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                            <div style="font-weight:600; font-size:14px; color:var(--text-heading);">Call Using CRM Softphone</div>
                            <span style="font-size:10px; background-color:#EFF6FF; color:var(--primary); padding:2px 6px; border-radius:12px; font-weight:600;">CRM</span>
                        </div>
                        <div style="font-size:12px; color:var(--text-muted);">Place the call using the built-in CRM dialer.</div>
                    </div>
                    <div style="color:var(--text-muted);">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                    </div>
                </div>

                <!-- Option 2: Device -->
                <div class="call-method-card" id="btnCallDevice" style="display:flex; align-items:center; gap:16px; padding:16px; border:1px solid var(--border-card); border-radius:10px; cursor:pointer; transition:all 0.2s;"
                     onmouseover="this.style.borderColor='var(--primary)'; this.style.backgroundColor='#F8FAFC';" 
                     onmouseout="this.style.borderColor='var(--border-card)'; this.style.backgroundColor='transparent';"
                     onclick="executeCallDevice()">
                    <div style="width:40px; height:40px; border-radius:8px; background-color:#F1F5F9; color:var(--text-secondary); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:600; font-size:14px; color:var(--text-heading); margin-bottom:4px;">Call Using Device</div>
                        <div style="font-size:12px; color:var(--text-muted);">Open your device's default calling application.</div>
                    </div>
                    <div style="color:var(--text-muted);">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="background:#F9FAFB; border-top: 1px solid var(--border-card); border-radius:0 0 12px 12px; padding: 14px 24px; display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary btn-md call-choice-cancel-btn" onclick="closeCallChoiceModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Modal 2: Redesigned Modern WhatsApp Side Drawer -->
<div class="whatsapp-overlay" id="whatsappModal" role="dialog" aria-modal="true" aria-labelledby="whatsappTitle">
    <div class="whatsapp-panel" id="whatsappPanel">
        <!-- Header -->
        <div class="whatsapp-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="whatsapp-icon-box">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                </div>
                <div>
                    <h3 class="whatsapp-drawer-title" id="whatsappTitle">New WhatsApp Message</h3>
                    <p style="font-size: 11px; color: var(--text-secondary);">Direct WhatsApp Web / App Integration</p>
                </div>
            </div>
            <button class="btn btn-ghost btn-xs" onclick="closeWhatsAppModal()" style="padding: 4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Body -->
        <div class="whatsapp-drawer-body">
            <div id="whatsappNotice" style="display: none; padding: 10px; border-radius: var(--radius-md); background-color: #FEF2F2; color: #F04438; font-size: 12px; font-weight: 500; margin-bottom: 12px; text-align: center;">
                No valid WhatsApp number is available for this lead.
            </div>

            <!-- Recipient Section Card -->
            <div class="whatsapp-recipient-card">
                <div class="avatar avatar-md" id="waAvatar" style="background-color: #7C3AED;">MT</div>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; font-size: 14px; color: var(--text-heading);" id="waRecipientName">Marcus Thompson</div>
                    <div style="font-size: 11px; color: var(--text-muted);" id="waRecipientCompany">Acme Corp</div>
                    <div style="font-size: 12px; font-weight: 600; color: #25D366; margin-top: 1px;" id="waRecipientPhone">+1 (415) 555-0123</div>
                </div>
                <span class="wa-status-badge">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #25D366;"></span>
                    WhatsApp Ready
                </span>
            </div>

            <!-- Template Selectors Chips -->
            <div class="whatsapp-templates-section" style="margin-bottom: 18px;">
                <label class="form-label" style="margin-bottom: 10px; display: block;">Message Templates</label>
                <div class="template-chips-container">
                    <button type="button" class="template-chip active" onclick="selectWATemplateChip(this, 'intro')">Initial Intro</button>
                    <button type="button" class="template-chip" onclick="selectWATemplateChip(this, 'followup')">Follow-up</button>
                    <button type="button" class="template-chip" onclick="selectWATemplateChip(this, 'meeting')">Meeting Conf</button>
                    <button type="button" class="template-chip" onclick="selectWATemplateChip(this, 'proposal')">Proposal</button>
                    <button type="button" class="template-chip" onclick="selectWATemplateChip(this, 'custom')">Custom</button>
                </div>
            </div>

            <!-- Personalization Tokens -->
            <div style="margin-bottom: 10px;">
                <span style="font-size: 11px; color: var(--text-muted); font-weight: 500;">Insert Tokens: </span>
                <button class="token-btn" onclick="insertWAToken('{first_name}')">{first_name}</button>
                <button class="token-btn" onclick="insertWAToken('{company}')">{company}</button>
                <button class="token-btn" onclick="insertWAToken('{representative}')">{representative}</button>
            </div>

            <!-- Textarea -->
            <div class="form-group" style="margin-bottom: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <label class="form-label">Message Content</label>
                    <span style="font-size: 11px; color: var(--text-muted);" id="waCharCount">0 / 1000</span>
                </div>
                <textarea class="input-control" id="waMessageTextarea" style="height: 110px; font-size: 13px; padding: 10px; line-height: 1.5; resize: none; border-radius: 12px;" oninput="updateWACharCount()"></textarea>
            </div>

            <!-- WhatsApp Chat Bubble Preview -->
            <div style="margin-bottom: 14px;">
                <span style="font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 6px;">Live Message Preview</span>
                <div class="wa-chat-bubble-container">
                    <div class="wa-chat-bubble">
                        <p id="waBubbleText">Hello Marcus, this is Sarah Chen from NexFlow...</p>
                        <div class="wa-bubble-footer">
                            <span id="waBubbleTime">10:42 AM</span>
                            <span class="wa-checks">✓✓</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wa-info-note">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>You'll review and send this message in WhatsApp Web or native app.</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="whatsapp-drawer-footer">
            <button class="btn btn-secondary btn-sm" onclick="closeWhatsAppModal()">Cancel</button>
            <button class="btn btn-primary btn-sm" id="btnContinueWA" style="background-color: #25D366; border: none;" onclick="executeContinueWhatsApp()">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                Continue to WhatsApp
            </button>
        </div>
    </div>
</div>

<!-- Modal 3: Redesigned Modern Email Modal with Context Sidebar -->
<div class="modal-overlay" id="emailModal" role="dialog" aria-modal="true" aria-labelledby="emailTitle">
    <div class="email-modal-container">
        <!-- Main Form Column -->
        <div class="email-main-column">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-divider); padding: 18px 24px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 36px; height: 36px; border-radius: var(--radius-md); background-color: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                    <div>
                        <h3 class="modal-title" id="emailTitle" style="font-size: 16px; font-weight: 600;">Compose Email</h3>
                        <p style="font-size: 12px; color: var(--text-secondary); margin-top: 1px;">Native Mail Client Linker</p>
                    </div>
                </div>
                <div style="display: flex; gap: 4px;">
                    <button class="btn btn-ghost btn-xs" onclick="closeEmailModal()" style="padding: 4px;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>

            <div class="modal-body" style="padding: 20px 24px; flex: 1; min-height: 0; overflow-y: auto;">
                <div id="emailNotice" style="display: none; padding: 10px 14px; border-radius: var(--radius-md); background-color: #FEF2F2; color: #F04438; font-size: 12.5px; font-weight: 500; margin-bottom: 14px; text-align: center;">
                    No email address is available for this lead.
                </div>

                <!-- Recipient To Chip -->
                <div class="email-recipient-chip-row" style="margin-bottom: 14px;">
                    <span class="email-field-label">To:</span>
                    <div class="email-to-chip" id="emailToChip">
                        <div class="avatar avatar-xs" id="emailChipAvatar" style="background-color: #7C3AED;">MT</div>
                        <span id="emailChipName">Marcus Thompson</span>
                        <span style="color: var(--text-muted);" id="emailChipEmail">&lt;marcus@acmecorp.io&gt;</span>
                    </div>
                    <button class="btn btn-ghost btn-xs" onclick="toggleEmailCcBcc()" style="font-size: 11.5px; color: var(--primary); font-weight: 600; margin-left: auto;">Cc / Bcc</button>
                </div>

                <!-- Collapsible CC/BCC -->
                <div id="emailCcBccFields" style="display: none; margin-bottom: 14px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px;">CC</label>
                            <input type="email" class="input-control input-sm" id="emailCcInput" placeholder="cc@company.com">
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 11.5px; margin-bottom: 4px;">BCC</label>
                            <input type="email" class="input-control input-sm" id="emailBccInput" placeholder="bcc@company.com">
                        </div>
                    </div>
                </div>

                <!-- Subject Row -->
                <div class="form-group" style="margin-bottom: 14px;">
                    <input type="text" class="input-control" id="emailSubjectInput" placeholder="Subject..." style="font-weight: 600; height: 38px; font-size: 13.5px; padding: 0 12px;">
                </div>

                <!-- Template Selector Chips -->
                <div style="margin-bottom: 16px;">
                    <div class="template-chips-container">
                        <button type="button" class="template-chip active" onclick="selectEmailTemplateChip(this, 'intro')">Initial Intro</button>
                        <button type="button" class="template-chip" onclick="selectEmailTemplateChip(this, 'followup')">Follow-up</button>
                        <button type="button" class="template-chip" onclick="selectEmailTemplateChip(this, 'meeting')">Meeting Conf</button>
                        <button type="button" class="template-chip" onclick="selectEmailTemplateChip(this, 'proposal')">Proposal</button>
                        <button type="button" class="template-chip" onclick="selectEmailTemplateChip(this, 'thanks')">Thank You</button>
                        <button type="button" class="template-chip" onclick="selectEmailTemplateChip(this, 'custom')">Custom</button>
                    </div>
                </div>

                <!-- Formatting Toolbar (Frontend Visual Representation) -->
                <div class="email-editor-toolbar">
                    <button type="button" class="toolbar-btn" title="Bold" onclick="showCommToast('Formatting: Bold applied')"><b>B</b></button>
                    <button type="button" class="toolbar-btn" title="Italic" onclick="showCommToast('Formatting: Italic applied')"><i>I</i></button>
                    <button type="button" class="toolbar-btn" title="Underline" onclick="showCommToast('Formatting: Underline applied')"><u>U</u></button>
                    <span class="toolbar-divider"></span>
                    <button type="button" class="toolbar-btn" title="Bullet List" onclick="showCommToast('Formatting: Bullet list')">• List</button>
                    <button type="button" class="toolbar-btn" title="Insert Link" onclick="showCommToast('Formatting: Link dialog')">🔗 Link</button>
                    <button type="button" class="toolbar-btn" title="Attach File" onclick="showCommToast('Attach: File selection dialog')">📎 Attach</button>
                    <div style="margin-left: auto; display: flex; gap: 6px;">
                        <button type="button" class="token-btn" onclick="insertEmailToken('{first_name}')">{first_name}</button>
                        <button type="button" class="token-btn" onclick="insertEmailToken('{company}')">{company}</button>
                    </div>
                </div>

                <!-- Main Textarea -->
                <div class="form-group" style="margin-bottom: 8px;">
                    <textarea class="input-control" id="emailBodyTextarea" style="min-height: 140px; height: 160px; font-size: 13.5px; padding: 12px; line-height: 1.55; resize: vertical; border-radius: 0 0 8px 8px; border-top: none;" oninput="updateEmailStats()"></textarea>
                </div>

                <!-- Word & Char Counter & Attachment Zone -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                    <span style="font-size: 11.5px; color: var(--text-muted);" id="emailStatsText">0 words • 0 characters</span>
                    <span style="font-size: 11.5px; color: var(--text-secondary);">📎 Drag & Drop Files (Demo)</span>
                </div>

                <div class="email-note-info">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span>Your default email application will open. You'll review and send the email there.</span>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer" style="border-top: 1px solid var(--border-divider); padding: 14px 20px 18px 20px; background-color: #FFFFFF; flex-shrink: 0; box-sizing: border-box;">
                <button type="button" class="btn btn-ghost btn-sm" onclick="saveEmailDraftSession()" style="padding: 8px 14px; font-weight: 500;">Save Draft</button>
                <div style="display: flex; gap: 10px; margin-left: auto;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeEmailModal()" style="padding: 8px 16px;">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnOpenEmailApp" onclick="executeOpenEmailApp()" style="padding: 8px 18px; font-weight: 600;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Open Email App
                    </button>
                </div>
            </div>
        </div>

        <!-- Contact Context Panel -->
        <div class="email-context-sidebar">
            <h4 style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 14px;">Contact Context</h4>
            <div class="avatar avatar-md" id="ctxAvatar" style="background-color: #7C3AED; margin-bottom: 8px; width: 44px; height: 44px; font-size: 15px;">MT</div>
            <div style="font-weight: 700; font-size: 15px; color: var(--text-heading);" id="ctxName">Marcus Thompson</div>
            <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;" id="ctxCompany">Acme Corp</p>
            
            <div style="margin-top: 18px; display: flex; flex-direction: column; gap: 12px;">
                <div style="background-color: #FFFFFF; border-radius: var(--radius-md); padding: 10px 12px; border: 1px solid var(--border-card);">
                    <span style="font-size: 10px; color: var(--text-muted); font-weight: 700; letter-spacing: 0.04em;">STATUS</span>
                    <p style="font-size: 12.5px; font-weight: 600; color: #059669; margin-top: 2px;" id="ctxStatus">Qualified</p>
                </div>
                <div style="background-color: #FFFFFF; border-radius: var(--radius-md); padding: 10px 12px; border: 1px solid var(--border-card);">
                    <span style="font-size: 10px; color: var(--text-muted); font-weight: 700; letter-spacing: 0.04em;">DEAL VALUE</span>
                    <p style="font-size: 13.5px; font-weight: 700; color: var(--text-heading); margin-top: 2px;" id="ctxValue">$24,000</p>
                </div>
                <div style="background-color: #FFFFFF; border-radius: var(--radius-md); padding: 10px 12px; border: 1px solid var(--border-card);">
                    <span style="font-size: 10px; color: var(--text-muted); font-weight: 700; letter-spacing: 0.04em;">LAST ACTIVITY</span>
                    <p style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;" id="ctxActivity">2h ago</p>
                </div>
            </div>
        </div>
    </div>
</div>

