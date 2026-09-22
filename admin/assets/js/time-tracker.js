/**
 * NexFlow CRM — Global Time Tracker & Timesheets JavaScript Controller
 * Frontend-only demonstration controller.
 * Persists active timer and completed timesheets to localStorage.
 * Runs across all admin CRM pages.
 */

(function () {
    'use strict';

    const TIMER_STORAGE_KEY = 'NexFlow_time_tracker_v1';
    const TIMESHEETS_STORAGE_KEY = 'NexFlow_timesheets_data_v1';

    // Default Demo Timesheet Records
    const defaultTimesheets = [
        {
            id: 'ts-2026-001',
            title: 'Acme SAML 2.0 SSO Architecture Discovery',
            relatedType: 'Lead',
            relatedRecord: 'Marcus Thompson (Acme Corp)',
            category: 'Solution Architecture',
            durationSeconds: 9900, // 2h 45m
            billable: true,
            hourlyRate: 150,
            date: new Date().toISOString().split('T')[0],
            startTime: '09:30 AM',
            endTime: '12:15 PM',
            owner: 'Olivia Martin',
            notes: 'Completed technical scoping for enterprise identity provider mapping.'
        },
        {
            id: 'ts-2026-002',
            title: 'Q3 Executive Pipeline Revenue Review',
            relatedType: 'General',
            relatedRecord: 'Internal Sales Operations',
            category: 'Internal Operations',
            durationSeconds: 4500, // 1h 15m
            billable: false,
            hourlyRate: 0,
            date: new Date().toISOString().split('T')[0],
            startTime: '01:00 PM',
            endTime: '02:15 PM',
            owner: 'Olivia Martin',
            notes: 'Reviewed pipeline stage conversion metrics.'
        },
        {
            id: 'ts-2026-003',
            title: 'CloudShift Operations Migration Webhook Staging Review',
            relatedType: 'Deal',
            relatedRecord: 'CloudShift Enterprise Migration',
            category: 'Technical Integration',
            durationSeconds: 12600, // 3h 30m
            billable: true,
            hourlyRate: 150,
            date: new Date(Date.now() - 86400000).toISOString().split('T')[0],
            startTime: '10:00 AM',
            endTime: '01:30 PM',
            owner: 'Daniel Reyes',
            notes: 'Tested database retry webhooks in staging sandbox.'
        },
        {
            id: 'ts-2026-004',
            title: 'BrightPath Pro SLA & Query Pipeline Check-in',
            relatedType: 'Company',
            relatedRecord: 'BrightPath Analytics',
            category: 'Account Management',
            durationSeconds: 3600, // 1h 00m
            billable: true,
            hourlyRate: 150,
            date: new Date(Date.now() - 86400000 * 2).toISOString().split('T')[0],
            startTime: '03:00 PM',
            endTime: '04:00 PM',
            owner: 'Olivia Martin',
            notes: 'Confirmed uptime guarantees for annual SLA renewal.'
        }
    ];

    // State
    let activeTimer = loadActiveTimer();
    let timesheets = loadTimesheets();
    let currentSearch = '';
    let currentTypeFilter = 'All';
    let currentBillableFilter = 'All';
    let activeTimesheetId = null;
    let tickerInterval = null;

    function loadActiveTimer() {
        try {
            const saved = localStorage.getItem(TIMER_STORAGE_KEY);
            if (saved) return JSON.parse(saved);
        } catch (e) {
            console.warn('Could not load active timer:', e);
        }
        return null;
    }

    function saveActiveTimer(timerObj) {
        activeTimer = timerObj;
        try {
            if (timerObj) {
                localStorage.setItem(TIMER_STORAGE_KEY, JSON.stringify(timerObj));
            } else {
                localStorage.removeItem(TIMER_STORAGE_KEY);
            }
        } catch (e) {
            console.warn('Could not save active timer:', e);
        }
        updateHeaderIndicator();
    }

    function loadTimesheets() {
        try {
            const saved = localStorage.getItem(TIMESHEETS_STORAGE_KEY);
            if (saved) return JSON.parse(saved);
        } catch (e) {
            console.warn('Could not load timesheets:', e);
        }
        return JSON.parse(JSON.stringify(defaultTimesheets));
    }

    function saveTimesheets() {
        try {
            localStorage.setItem(TIMESHEETS_STORAGE_KEY, JSON.stringify(timesheets));
        } catch (e) {
            console.warn('Could not save timesheets:', e);
        }
        updateDrawerMetrics();
    }

    // Time Calculations
    function getElapsedSeconds(timer) {
        if (!timer) return 0;
        let elapsedMs = 0;
        if (timer.isRunning) {
            elapsedMs = Date.now() - timer.startTime - (timer.accumulatedPausedMs || 0);
        } else {
            elapsedMs = (timer.pauseTime || Date.now()) - timer.startTime - (timer.accumulatedPausedMs || 0);
        }
        return Math.max(0, Math.floor(elapsedMs / 1000));
    }

    function formatSeconds(totalSecs) {
        const hrs = Math.floor(totalSecs / 3600);
        const mins = Math.floor((totalSecs % 3600) / 60);
        const secs = totalSecs % 60;
        return `${String(hrs).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    function formatDurationHuman(totalSecs) {
        const hrs = Math.floor(totalSecs / 3600);
        const mins = Math.floor((totalSecs % 3600) / 60);
        if (hrs === 0) return `${mins}m`;
        return `${hrs}h ${mins}m`;
    }

    // Global Header Indicator
    function updateHeaderIndicator() {
        const btn = document.getElementById('topbarTimerBtn');
        const badge = document.getElementById('topbarTimerActiveBadge');
        const digits = document.getElementById('topbarTimerDigits');
        const popover = document.getElementById('timeTrackerPopover');

        if (!btn || !badge || !digits) return;

        if (activeTimer) {
            btn.classList.add('active');
            badge.style.display = 'inline-flex';
            const elapsed = getElapsedSeconds(activeTimer);
            digits.textContent = formatSeconds(elapsed);

            // If popover is open, update running timer elements
            if (popover && popover.classList.contains('show')) {
                const popoverClock = document.getElementById('popoverRunningClock');
                if (popoverClock) popoverClock.textContent = formatSeconds(elapsed);
            }
        } else {
            btn.classList.remove('active');
            badge.style.display = 'none';
        }
    }

    // Public Controller Window Binding
    window.NexFlowTimer = {
        init: function () {
            updateHeaderIndicator();
            this.setupEventListeners();
            this.startTicker();
        },

        startTicker: function () {
            if (tickerInterval) clearInterval(tickerInterval);
            tickerInterval = setInterval(() => {
                if (activeTimer && activeTimer.isRunning) {
                    updateHeaderIndicator();
                }
            }, 1000);
        },

        setupEventListeners: function () {
            document.addEventListener('click', function (e) {
                const popover = document.getElementById('timeTrackerPopover');
                const btn = document.getElementById('topbarTimerBtn');
                if (popover && popover.classList.contains('show')) {
                    if (!popover.contains(e.target) && !btn.contains(e.target)) {
                        popover.classList.remove('show');
                    }
                }
                if (!e.target.closest('#timesheetRowDropdown') && !e.target.closest('.ts-row-menu-btn')) {
                    const rowMenu = document.getElementById('timesheetRowDropdown');
                    if (rowMenu) rowMenu.classList.remove('show');
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('timerModalOverlay');
                    if (modal && modal.classList.contains('show')) {
                        window.NexFlowTimer.closeModal();
                        e.stopPropagation();
                        return;
                    }
                    const drawer = document.getElementById('timesheetsDrawer');
                    if (drawer && drawer.classList.contains('show')) {
                        window.NexFlowTimer.closeTimesheetsDrawer();
                        e.stopPropagation();
                        return;
                    }
                    const popover = document.getElementById('timeTrackerPopover');
                    if (popover && popover.classList.contains('show')) {
                        popover.classList.remove('show');
                        e.stopPropagation();
                        return;
                    }
                }
            });
        },

        togglePopover: function (e) {
            if (e) e.stopPropagation();
            const popover = document.getElementById('timeTrackerPopover');
            const btn = document.getElementById('topbarTimerBtn');
            if (!popover || !btn) return;

            if (popover.classList.contains('show')) {
                popover.classList.remove('show');
                return;
            }

            // Position popover below button
            const rect = btn.getBoundingClientRect();
            popover.style.top = `${rect.bottom + 8}px`;
            popover.style.left = `${Math.min(rect.left - 200, window.innerWidth - 380)}px`;

            this.renderPopoverContent();
            popover.classList.add('show');
        },

        renderPopoverContent: function () {
            const container = document.getElementById('timeTrackerPopoverContent');
            if (!container) return;

            if (!activeTimer) {
                container.innerHTML = `
                    <div class="timer-empty-state">
                        <div class="timer-empty-icon">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <h4 class="timer-empty-title">No active timer</h4>
                        <p class="timer-empty-desc">Track time spent on tasks, leads, deals, and customer meetings.</p>
                        <button type="button" class="btn btn-primary btn-sm" style="width:100%;margin-bottom:10px;" onclick="window.NexFlowTimer.openStartTimerModal()">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            Start Timer
                        </button>
                        <a href="javascript:void(0)" style="font-size:12.5px;color:var(--primary);font-weight:600;text-decoration:none;" onclick="window.NexFlowTimer.openTimesheetsDrawer()">
                            View Timesheets
                        </a>
                    </div>
                `;
            } else {
                const elapsed = getElapsedSeconds(activeTimer);
                const startTimeStr = new Date(activeTimer.startTime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                container.innerHTML = `
                    <div class="timer-running-card">
                        <div class="timer-running-header">
                            <span style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:${activeTimer.isRunning ? '#047857' : '#B45309'};">
                                <span class="timer-pulse-dot" style="${activeTimer.isRunning ? '' : 'animation:none;background:#F59E0B;'}"></span>
                                ${activeTimer.isRunning ? 'TIMER RUNNING' : 'TIMER PAUSED'}
                            </span>
                            ${activeTimer.billable ? '<span class="badge badge-success" style="font-size:11px;">Billable</span>' : '<span class="badge badge-secondary" style="font-size:11px;">Non-Billable</span>'}
                        </div>
                        
                        <div>
                            <div class="timer-live-clock" id="popoverRunningClock">${formatSeconds(elapsed)}</div>
                            <h4 class="timer-running-title">${activeTimer.title}</h4>
                            <div class="timer-running-sub">
                                <span class="badge badge-primary" style="font-size:10.5px;">${activeTimer.relatedType}</span>
                                <span>${activeTimer.relatedRecord}</span>
                            </div>
                        </div>

                        <div style="font-size:11.5px;color:var(--text-muted);border-top:1px solid var(--border-divider);padding-top:8px;">
                            Started at ${startTimeStr}
                        </div>

                        <div class="timer-running-controls">
                            ${activeTimer.isRunning ? `
                                <button type="button" class="btn btn-secondary btn-sm" style="flex:1;" onclick="window.NexFlowTimer.pauseTimer()">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                                    Pause
                                </button>
                            ` : `
                                <button type="button" class="btn btn-primary btn-sm" style="flex:1;" onclick="window.NexFlowTimer.resumeTimer()">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    Resume
                                </button>
                            `}
                            <button type="button" class="btn btn-danger btn-sm" style="flex:1;" onclick="window.NexFlowTimer.promptStopTimer()">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/></svg>
                                Stop Timer
                            </button>
                        </div>

                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:2px;">
                            <a href="javascript:void(0)" style="font-size:11.5px;color:#DC2626;" onclick="window.NexFlowTimer.discardTimer()">Discard Timer</a>
                            <a href="javascript:void(0)" style="font-size:11.5px;color:var(--primary);font-weight:600;" onclick="window.NexFlowTimer.openTimesheetsDrawer()">View Timesheets</a>
                        </div>
                    </div>
                `;
            }
        },

        // Start Timer Modal
        openStartTimerModal: function () {
            const popover = document.getElementById('timeTrackerPopover');
            if (popover) popover.classList.remove('show');

            const html = `
                <div class="timer-modal-header">
                    <h3 class="timer-modal-title">Start Time Tracker</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.NexFlowTimer.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.NexFlowTimer.submitStartTimer(event)">
                    <div class="timer-modal-body">
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Timer Title / Activity *</label>
                            <input type="text" class="input-control" id="startTimerTitle" placeholder="e.g. Scoping call with Marcus Thompson" required autofocus>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Related Type</label>
                                <select class="input-control" id="startTimerType" onchange="window.NexFlowTimer.handleRelatedTypeChange(this.value)">
                                    <option value="Lead">Lead</option>
                                    <option value="Deal">Deal</option>
                                    <option value="Task">Task</option>
                                    <option value="Contact">Contact</option>
                                    <option value="Company">Company</option>
                                    <option value="General">General</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Related Record</label>
                                <input type="text" class="input-control" id="startTimerRecord" placeholder="e.g. Marcus Thompson (Acme Corp)" value="Marcus Thompson (Acme Corp)" required>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Category</label>
                                <select class="input-control" id="startTimerCategory">
                                    <option>Discovery &amp; Scoping</option>
                                    <option>Technical Consultation</option>
                                    <option>Contract Review</option>
                                    <option>Support &amp; Troubleshooting</option>
                                    <option>Internal Operations</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Hourly Rate ($)</label>
                                <input type="number" class="input-control" id="startTimerRate" value="150">
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;cursor:pointer;">
                                <input type="checkbox" class="input-checkbox" id="startTimerBillable" checked>
                                <span>Billable to customer</span>
                            </label>
                        </div>
                        <div>
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="input-control" id="startTimerNotes" rows="2" style="height:55px;resize:none;padding: 6px 10px" placeholder="Additional context..."></textarea>
                        </div>
                    </div>
                    <div class="timer-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.NexFlowTimer.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Start Tracking</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        handleRelatedTypeChange: function (typeVal) {
            const input = document.getElementById('startTimerRecord');
            if (!input) return;
            if (typeVal === 'Lead') input.value = 'Marcus Thompson (Acme Corp)';
            else if (typeVal === 'Deal') input.value = 'CloudShift Operations Migration';
            else if (typeVal === 'Task') input.value = 'Prepare Sales Deck for Acme';
            else if (typeVal === 'Company') input.value = 'BrightPath Analytics';
            else if (typeVal === 'Contact') input.value = 'Elena Rostova';
            else input.value = 'Internal Operations';
        },

        submitStartTimer: function (e) {
            e.preventDefault();
            const title = document.getElementById('startTimerTitle').value.trim();
            const relatedType = document.getElementById('startTimerType').value;
            const relatedRecord = document.getElementById('startTimerRecord').value.trim();
            const category = document.getElementById('startTimerCategory').value;
            const rate = parseFloat(document.getElementById('startTimerRate').value) || 0;
            const billable = document.getElementById('startTimerBillable').checked;
            const notes = document.getElementById('startTimerNotes').value.trim();

            if (!title) return;

            const timerObj = {
                id: 'timer_' + Date.now(),
                title: title,
                relatedType: relatedType,
                relatedRecord: relatedRecord,
                category: category,
                hourlyRate: rate,
                billable: billable,
                notes: notes,
                startTime: Date.now(),
                accumulatedPausedMs: 0,
                isRunning: true,
                pauseTime: null
            };

            saveActiveTimer(timerObj);
            this.closeModal();
            this.togglePopover();
            this.showToast(`Timer started: "${title}"`, 'success');
        },

        pauseTimer: function () {
            if (!activeTimer || !activeTimer.isRunning) return;
            activeTimer.isRunning = false;
            activeTimer.pauseTime = Date.now();
            saveActiveTimer(activeTimer);
            this.renderPopoverContent();
            this.showToast('Timer paused.', 'info');
        },

        resumeTimer: function () {
            if (!activeTimer || activeTimer.isRunning) return;
            if (activeTimer.pauseTime) {
                const pausedDuration = Date.now() - activeTimer.pauseTime;
                activeTimer.accumulatedPausedMs = (activeTimer.accumulatedPausedMs || 0) + pausedDuration;
            }
            activeTimer.isRunning = true;
            activeTimer.pauseTime = null;
            saveActiveTimer(activeTimer);
            this.renderPopoverContent();
            this.showToast('Timer resumed.', 'success');
        },

        promptStopTimer: function () {
            if (!activeTimer) return;
            const elapsed = getElapsedSeconds(activeTimer);
            const durationStr = formatDurationHuman(elapsed);

            const popover = document.getElementById('timeTrackerPopover');
            if (popover) popover.classList.remove('show');

            const html = `
                <div class="timer-modal-header">
                    <h3 class="timer-modal-title">Stop Timer &amp; Save Entry</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.NexFlowTimer.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="timer-modal-body">
                    <p style="font-size:13.5px;color:var(--text-body);line-height:1.5;">
                        Save completed time entry for <strong>"${activeTimer.title}"</strong> (${activeTimer.relatedRecord})?
                    </p>
                    <div style="background:#F8FAFC;border:1px solid var(--border-divider);border-radius:8px;padding:14px;display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-weight:600;font-size:13px;color:var(--text-heading);">Total Tracked Duration:</span>
                        <span style="font-size:18px;font-weight:700;color:var(--primary);">${durationStr} (${formatSeconds(elapsed)})</span>
                    </div>
                </div>
                <div class="timer-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.NexFlowTimer.closeModal()">Keep Running</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.NexFlowTimer.confirmStopTimer()">Save Timesheet</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmStopTimer: function () {
            if (!activeTimer) return;
            const elapsed = getElapsedSeconds(activeTimer);
            const now = new Date();
            const start = new Date(activeTimer.startTime);

            const timesheetEntry = {
                id: 'ts-' + Date.now(),
                title: activeTimer.title,
                relatedType: activeTimer.relatedType,
                relatedRecord: activeTimer.relatedRecord,
                category: activeTimer.category,
                durationSeconds: elapsed,
                billable: activeTimer.billable,
                hourlyRate: activeTimer.hourlyRate,
                date: now.toISOString().split('T')[0],
                startTime: start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                endTime: now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                owner: 'Olivia Martin',
                notes: activeTimer.notes || ''
            };

            timesheets.unshift(timesheetEntry);
            saveTimesheets();
            saveActiveTimer(null);
            this.closeModal();
            this.showToast(`Timesheet entry saved (${formatDurationHuman(elapsed)}).`, 'success');
        },

        discardTimer: function () {
            saveActiveTimer(null);
            const popover = document.getElementById('timeTrackerPopover');
            if (popover) popover.classList.remove('show');
            this.showToast('Timer discarded.', 'info');
        },

        // Timesheets Drawer
        openTimesheetsDrawer: function () {
            const popover = document.getElementById('timeTrackerPopover');
            if (popover) popover.classList.remove('show');

            const drawer = document.getElementById('timesheetsDrawer');
            if (drawer) {
                drawer.style.display = 'block';
                drawer.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
            this.renderTimesheets();
            updateDrawerMetrics();
        },

        closeTimesheetsDrawer: function () {
            const drawer = document.getElementById('timesheetsDrawer');
            if (drawer) {
                drawer.classList.remove('show');
                drawer.style.display = 'none';
                document.body.style.overflow = '';
            }
        },

        handleSearch: function (val) {
            currentSearch = (val || '').toLowerCase().trim();
            this.renderTimesheets();
        },

        handleTypeFilter: function (typeVal) {
            currentTypeFilter = typeVal;
            this.renderTimesheets();
        },

        handleBillableFilter: function (bVal) {
            currentBillableFilter = bVal;
            this.renderTimesheets();
        },

        renderTimesheets: function () {
            this.closeRowMenu();
            const listContainer = document.getElementById('timesheetsEntriesList');
            if (!listContainer) return;

            let list = timesheets.filter(ts => {
                const matchesType = (currentTypeFilter === 'All') || (ts.relatedType === currentTypeFilter);
                const matchesBillable = (currentBillableFilter === 'All') ||
                    (currentBillableFilter === 'Billable' && ts.billable) ||
                    (currentBillableFilter === 'Non-Billable' && !ts.billable);
                const matchesSearch = !currentSearch ||
                    ts.title.toLowerCase().includes(currentSearch) ||
                    ts.relatedRecord.toLowerCase().includes(currentSearch) ||
                    ts.owner.toLowerCase().includes(currentSearch);

                return matchesType && matchesBillable && matchesSearch;
            });

            if (list.length === 0) {
                listContainer.innerHTML = `
                    <div style="text-align:center;padding:36px;color:var(--text-secondary);font-size:13px;">
                        No timesheet entries match current filters.
                    </div>
                `;
                return;
            }

            listContainer.innerHTML = list.map(ts => `
                <div class="timesheet-entry-card" data-ts-id="${ts.id}">
                    <div class="timesheet-entry-info">
                        <div class="timesheet-entry-title">${ts.title}</div>
                        <div class="timesheet-entry-meta">
                            <span class="badge badge-primary" style="font-size:10px;">${ts.relatedType}</span>
                            <span>${ts.relatedRecord}</span>
                            <span>• ${ts.date} (${ts.startTime} - ${ts.endTime})</span>
                        </div>
                    </div>
                    <div class="timesheet-entry-duration">
                        <span class="timesheet-duration-digits">${formatDurationHuman(ts.durationSeconds)}</span>
                        ${ts.billable ? '<span class="badge badge-success" style="font-size:10px;">Billable</span>' : '<span class="badge badge-secondary" style="font-size:10px;">Non-Billable</span>'}
                    </div>
                    <button type="button" class="btn btn-ghost btn-xs ts-row-menu-btn" onclick="window.NexFlowTimer.openRowMenu(event, '${ts.id}')">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                    </button>
                </div>
            `).join('');
        },

        openRowMenu: function (e, tsId) {
            e.stopPropagation();

            const menu = document.getElementById('timesheetRowDropdown');

            // If clicking the same three-dot button while open, toggle close
            if (activeTimesheetId === tsId && menu && menu.classList.contains('show')) {
                this.closeRowMenu();
                return;
            }

            // Close any open menu before opening the new one
            this.closeRowMenu();

            const ts = timesheets.find(t => t.id === tsId);
            if (!ts || !menu) return;

            activeTimesheetId = tsId;

            const btn = e.currentTarget;
            btn.classList.add('active');

            menu.innerHTML = `
                <button type="button" class="ts-dropdown-item" onclick="window.NexFlowTimer.closeRowMenu(); window.NexFlowTimer.openEditEntryModal('${ts.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Entry
                </button>
                <button type="button" class="ts-dropdown-item" onclick="window.NexFlowTimer.closeRowMenu(); window.NexFlowTimer.duplicateEntry('${ts.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Duplicate Entry
                </button>
                <button type="button" class="ts-dropdown-item" onclick="window.NexFlowTimer.closeRowMenu(); window.NexFlowTimer.toggleBillable('${ts.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    ${ts.billable ? 'Mark Non-Billable' : 'Mark Billable'}
                </button>
                <div class="ts-dropdown-divider"></div>
                <button type="button" class="ts-dropdown-item danger" onclick="window.NexFlowTimer.closeRowMenu(); window.NexFlowTimer.promptDeleteEntry('${ts.id}')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    Delete Entry
                </button>
            `;

            menu.style.display = 'block';
            const rect = btn.getBoundingClientRect();
            const menuHeight = menu.offsetHeight || 165;
            const windowHeight = window.innerHeight;

            let top = rect.bottom + 4;
            if (top + menuHeight > windowHeight - 16) {
                top = Math.max(10, rect.top - menuHeight - 4);
            }

            let left = Math.min(rect.right - 200, window.innerWidth - 210);
            if (left < 10) left = 10;

            menu.style.position = 'fixed';
            menu.style.zIndex = '1200';
            menu.style.top = `${top}px`;
            menu.style.left = `${left}px`;
            menu.classList.add('show');
        },

        closeRowMenu: function () {
            activeTimesheetId = null;
            const menu = document.getElementById('timesheetRowDropdown');
            if (menu) {
                menu.classList.remove('show');
                menu.style.display = 'none';
            }
            document.querySelectorAll('.ts-row-menu-btn').forEach(b => b.classList.remove('active'));
        },

        // Manual Time Entry Modal
        openManualEntryModal: function () {
            const html = `
                <div class="timer-modal-header">
                    <h3 class="timer-modal-title">Manual Time Entry</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.NexFlowTimer.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.NexFlowTimer.submitManualEntry(event)">
                    <div class="timer-modal-body">
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Activity Description *</label>
                            <input type="text" class="input-control" id="manualTitle" placeholder="e.g. Solution architecture review" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Related Type</label>
                                <select class="input-control" id="manualType">
                                    <option value="Lead">Lead</option>
                                    <option value="Deal">Deal</option>
                                    <option value="Task">Task</option>
                                    <option value="Contact">Contact</option>
                                    <option value="Company">Company</option>
                                    <option value="General">General</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Related Record</label>
                                <input type="text" class="input-control" id="manualRecord" placeholder="e.g. Acme Corp" required>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Date</label>
                                <input type="date" class="input-control" id="manualDate" value="${new Date().toISOString().split('T')[0]}" required>
                            </div>
                            <div>
                                <label class="form-label">Duration (Hours) *</label>
                                <input type="number" step="0.25" class="input-control" id="manualHours" placeholder="1.5" value="1.5" required>
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;cursor:pointer;">
                                <input type="checkbox" class="input-checkbox" id="manualBillable" checked>
                                <span>Billable to customer</span>
                            </label>
                        </div>
                    </div>
                    <div class="timer-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.NexFlowTimer.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Entry</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitManualEntry: function (e) {
            e.preventDefault();
            const title = document.getElementById('manualTitle').value.trim();
            const type = document.getElementById('manualType').value;
            const record = document.getElementById('manualRecord').value.trim();
            const date = document.getElementById('manualDate').value;
            const hours = parseFloat(document.getElementById('manualHours').value) || 1;
            const billable = document.getElementById('manualBillable').checked;

            if (!title || !record) return;

            const entry = {
                id: 'ts-' + Date.now(),
                title: title,
                relatedType: type,
                relatedRecord: record,
                category: 'Manual Entry',
                durationSeconds: Math.round(hours * 3600),
                billable: billable,
                hourlyRate: 150,
                date: date,
                startTime: 'Manual',
                endTime: 'Manual',
                owner: 'Olivia Martin',
                notes: ''
            };

            timesheets.unshift(entry);
            saveTimesheets();
            this.closeModal();
            this.renderTimesheets();
            this.showToast('Manual timesheet entry created.', 'success');
        },

        openEditEntryModal: function (tsId) {
            const ts = timesheets.find(t => t.id === tsId);
            if (!ts) return;

            const hours = (ts.durationSeconds / 3600).toFixed(2);

            const html = `
                <div class="timer-modal-header">
                    <h3 class="timer-modal-title">Edit Timesheet Entry</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.NexFlowTimer.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form onsubmit="window.NexFlowTimer.submitEditEntry(event, '${ts.id}')">
                    <div class="timer-modal-body">
                        <div style="margin-bottom:12px;">
                            <label class="form-label">Activity Description *</label>
                            <input type="text" class="input-control" id="editTsTitle" value="${ts.title}" required>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Related Type</label>
                                <select class="input-control" id="editTsType">
                                    <option value="Lead" ${ts.relatedType === 'Lead' ? 'selected' : ''}>Lead</option>
                                    <option value="Deal" ${ts.relatedType === 'Deal' ? 'selected' : ''}>Deal</option>
                                    <option value="Task" ${ts.relatedType === 'Task' ? 'selected' : ''}>Task</option>
                                    <option value="Contact" ${ts.relatedType === 'Contact' ? 'selected' : ''}>Contact</option>
                                    <option value="Company" ${ts.relatedType === 'Company' ? 'selected' : ''}>Company</option>
                                    <option value="General" ${ts.relatedType === 'General' ? 'selected' : ''}>General</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Related Record *</label>
                                <input type="text" class="input-control" id="editTsRecord" value="${ts.relatedRecord}" required>
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label class="form-label">Date *</label>
                                <input type="date" class="input-control" id="editTsDate" value="${ts.date}" required>
                            </div>
                            <div>
                                <label class="form-label">Duration (Hours) *</label>
                                <input type="number" step="0.25" class="input-control" id="editTsHours" value="${hours}" required>
                            </div>
                        </div>
                        <div style="margin-bottom:12px;">
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;cursor:pointer;">
                                <input type="checkbox" class="input-checkbox" id="editTsBillable" ${ts.billable ? 'checked' : ''}>
                                <span>Billable to customer</span>
                            </label>
                        </div>
                    </div>
                    <div class="timer-modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.NexFlowTimer.closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                </form>
            `;
            this.openModal(html);
        },

        submitEditEntry: function (e, tsId) {
            e.preventDefault();
            const ts = timesheets.find(t => t.id === tsId);
            if (!ts) return;

            ts.title = document.getElementById('editTsTitle').value.trim();
            ts.relatedType = document.getElementById('editTsType').value;
            ts.relatedRecord = document.getElementById('editTsRecord').value.trim();
            ts.date = document.getElementById('editTsDate').value;
            const hrs = parseFloat(document.getElementById('editTsHours').value) || 1;
            ts.durationSeconds = Math.round(hrs * 3600);
            ts.billable = document.getElementById('editTsBillable').checked;

            saveTimesheets();
            this.closeModal();
            this.renderTimesheets();
            this.showToast('Timesheet entry updated.', 'success');
        },

        duplicateEntry: function (tsId) {
            const ts = timesheets.find(t => t.id === tsId);
            if (!ts) return;
            const copy = JSON.parse(JSON.stringify(ts));
            copy.id = 'ts-' + Date.now();
            copy.title = `${ts.title} (Copy)`;
            copy.date = new Date().toISOString().split('T')[0];
            timesheets.unshift(copy);
            saveTimesheets();
            this.renderTimesheets();
            this.showToast(`Duplicated as "${copy.title}".`, 'info');
        },

        toggleBillable: function (tsId) {
            const ts = timesheets.find(t => t.id === tsId);
            if (!ts) return;
            ts.billable = !ts.billable;
            saveTimesheets();
            this.renderTimesheets();
            this.showToast(`Marked as ${ts.billable ? 'Billable' : 'Non-Billable'}.`, 'info');
        },

        promptDeleteEntry: function (tsId) {
            const ts = timesheets.find(t => t.id === tsId);
            if (!ts) return;

            const durationStr = formatDurationHuman(ts.durationSeconds || 0);

            const html = `
                <div class="timer-modal-header">
                    <h3 class="timer-modal-title">Delete Time Entry?</h3>
                    <button type="button" class="btn btn-ghost btn-xs" onclick="window.NexFlowTimer.closeModal()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="timer-modal-body">
                    <p style="font-size:13.5px;color:var(--text-body);line-height:1.5;margin:0 0 14px;">
                        Are you sure you want to delete this time entry? This action cannot be undone.
                    </p>
                    <div style="background:#F8FAFC;border:1px solid var(--border-divider);border-radius:8px;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <div style="font-weight:600;font-size:13px;color:var(--text-heading);">${ts.title}</div>
                            <div style="font-size:11.5px;color:var(--text-secondary);margin-top:2px;">${ts.relatedRecord} • ${ts.date}</div>
                        </div>
                        <span style="font-size:15px;font-weight:700;color:var(--primary);">${durationStr}</span>
                    </div>
                </div>
                <div class="timer-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.NexFlowTimer.closeModal()">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" style="background-color:#F04438;color:#FFFFFF;border:none;" onclick="window.NexFlowTimer.confirmDeleteEntry('${ts.id}')">Delete Entry</button>
                </div>
            `;
            this.openModal(html);
        },

        confirmDeleteEntry: function (tsId) {
            timesheets = timesheets.filter(t => t.id !== tsId);
            saveTimesheets();
            this.closeModal();
            this.renderTimesheets();
            this.showToast('Timesheet entry deleted.', 'info');
        },

        exportTimesheetsCSV: function () {
            if (timesheets.length === 0) {
                this.showToast('No timesheet entries to export.', 'warning');
                return;
            }
            let csv = 'ID,Title,Type,Record,Category,DurationHours,Billable,Date,StartTime,EndTime,Owner\n';
            timesheets.forEach(ts => {
                const hrs = (ts.durationSeconds / 3600).toFixed(2);
                csv += `"${ts.id}","${ts.title}","${ts.relatedType}","${ts.relatedRecord}","${ts.category || ''}",${hrs},"${ts.billable ? 'Yes' : 'No'}","${ts.date}","${ts.startTime}","${ts.endTime}","${ts.owner}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('href', url);
            a.setAttribute('download', `NexFlow_timesheets_${new Date().toISOString().split('T')[0]}.csv`);
            a.click();
            window.URL.revokeObjectURL(url);
            this.showToast('Timesheets exported to CSV.', 'success');
        },

        // Top-Level Modal Root Management
        openModal: function (htmlContent) {
            const overlay = document.getElementById('timerModalOverlay');
            const card = document.getElementById('timerModalCard');
            if (overlay && card) {
                card.innerHTML = htmlContent;
                overlay.style.display = 'flex';
                overlay.classList.add('show');
            }
        },

        closeModal: function () {
            const overlay = document.getElementById('timerModalOverlay');
            if (overlay) {
                overlay.classList.remove('show');
                overlay.style.display = 'none';
            }
        },

        showToast: function (msg, type = 'info') {
            const container = document.getElementById('timerToastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `timer-toast ${type}`;
            toast.innerHTML = `<span>${msg}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.25s ease';
                setTimeout(() => toast.remove(), 250);
            }, 3200);
        }
    };

    function updateDrawerMetrics() {
        const todayStr = new Date().toISOString().split('T')[0];
        let todaySecs = 0;
        let weekSecs = 0;
        let billableSecs = 0;
        let nonBillableSecs = 0;

        timesheets.forEach(ts => {
            const secs = ts.durationSeconds || 0;
            if (ts.date === todayStr) todaySecs += secs;
            weekSecs += secs;
            if (ts.billable) billableSecs += secs;
            else nonBillableSecs += secs;
        });

        const elToday = document.getElementById('tsMetricToday');
        const elWeek = document.getElementById('tsMetricWeek');
        const elBillable = document.getElementById('tsMetricBillable');
        const elNonBillable = document.getElementById('tsMetricNonBillable');

        if (elToday) elToday.textContent = formatDurationHuman(todaySecs);
        if (elWeek) elWeek.textContent = formatDurationHuman(weekSecs);
        if (elBillable) elBillable.textContent = formatDurationHuman(billableSecs);
        if (elNonBillable) elNonBillable.textContent = formatDurationHuman(nonBillableSecs);
    }

    document.addEventListener('click', function (e) {
        const menu = document.getElementById('timesheetRowDropdown');
        if (menu && menu.classList.contains('show')) {
            if (!menu.contains(e.target) && !e.target.closest('.ts-row-menu-btn')) {
                window.NexFlowTimer.closeRowMenu();
            }
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const menu = document.getElementById('timesheetRowDropdown');
            if (menu && menu.classList.contains('show')) {
                window.NexFlowTimer.closeRowMenu();
            }
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.NexFlowTimer.init());
    } else {
        window.NexFlowTimer.init();
    }
})();

