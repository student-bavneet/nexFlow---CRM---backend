/**
 * NexFlow CRM Sales Calendar Controller JavaScript
 * Fully Database-Driven with Multi-Tenant Isolation & Task Deadlines Integration.
 * Handles view switching (Month, Week, Day, Agenda), date navigation (Today, Prev, Next),
 * mini-calendar date selection, live database filters (My Schedule, Team, Event Types, Assignees),
 * anchored event popovers, create/edit event drawers, native HTML5 drag-and-drop rescheduling,
 * task deadlines protection, and real contact profile preview.
 */

(function() {
    const VIEW_MODE_KEY = "NexFlow_calendar_view_mode";

    let allEvents = [];
    let filteredEvents = [];
    let referenceData = {
        users: [],
        companies: [],
        contacts: [],
        deals: [],
        current_user: null
    };

    // Current View State (Real current date)
    let currentDate = new Date();
    let selectedDate = new Date();
    let miniCalDate = new Date();

    let viewMode = "month"; // 'month', 'week', 'day', 'agenda'
    let activePopoverEvent = null;
    let lastFocusedElement = null;
    let currentDrawerContact = null;
    let draggedCalendarEventId = null;

    // Filters
    let calendarFilters = {
        my_schedule: true,
        team_schedule: true,
        sales_activities: true,
        task_deadlines: true
    };

    let eventTypeFilters = {
        Call: true,
        Meeting: true,
        "Product Demo": true,
        "Follow-up": true,
        Proposal: true,
        "Task Deadline": true,
        "Team Event": true
    };

    let ownerFilters = {};

    function initCalendarPage(forceReinit) {
        const root = document.querySelector(".calendar-page");
        if (!root) return; // Exit safely if not on Calendar page

        if (window._calendarPageInitialized && !forceReinit) return;
        window._calendarPageInitialized = true;

        setupEventListeners();

        // Responsive default mode
        if (window.innerWidth <= 768) {
            viewMode = "agenda";
        }

        const savedMode = localStorage.getItem(VIEW_MODE_KEY);
        if (savedMode && (savedMode === "month" || savedMode === "week" || savedMode === "day" || savedMode === "agenda")) {
            if (window.innerWidth > 768) viewMode = savedMode;
        }

        updateViewSwitcherButtons();

        // Bootstrap reference data and live calendar events from database
        loadReferenceOptions().then(() => {
            loadCalendarEvents();
        });
    }

    window.initCalendarPage = initCalendarPage;

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function() { initCalendarPage(); });
    } else {
        initCalendarPage();
    }

    // =========================================================================
    // DATA FETCHING & SYNCHRONIZATION
    // =========================================================================

    function loadReferenceOptions() {
        return fetch("api/calendar.php?action=reference_options")
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    referenceData = res.data;
                    populateReferenceDropdowns();
                }
            })
            .catch(err => {
                console.error("Failed to load calendar reference options:", err);
            });
    }

    function populateReferenceDropdowns() {
        // 1. Related Contacts Select
        const contactSelect = document.getElementById("addEvtContact");
        if (contactSelect) {
            let html = '<option value="">-- None --</option>';
            if (Array.isArray(referenceData.contacts)) {
                referenceData.contacts.forEach(c => {
                    html += `<option value="${c.id}">${escapeHtml(c.name)}${c.title ? ' (' + escapeHtml(c.title) + ')' : ''}</option>`;
                });
            }
            contactSelect.innerHTML = html;
        }

        // 2. Related Companies Select
        const companySelect = document.getElementById("addEvtCompany");
        if (companySelect) {
            let html = '<option value="">-- None --</option>';
            if (Array.isArray(referenceData.companies)) {
                referenceData.companies.forEach(comp => {
                    html += `<option value="${comp.id}">${escapeHtml(comp.name)}</option>`;
                });
            }
            companySelect.innerHTML = html;
        }

        // 3. Related Deals Select
        const dealSelect = document.getElementById("addEvtDeal");
        if (dealSelect) {
            let html = '<option value="">-- None --</option>';
            if (Array.isArray(referenceData.deals)) {
                referenceData.deals.forEach(d => {
                    const code = d.deal_code ? ` [${d.deal_code}]` : '';
                    html += `<option value="${d.id}">${escapeHtml(d.name)}${code}</option>`;
                });
            }
            dealSelect.innerHTML = html;
        }

        // 4. Assigned Owners Select
        const ownerSelect = document.getElementById("addEvtOwner");
        if (ownerSelect) {
            let html = '<option value="">-- Select Owner --</option>';
            if (Array.isArray(referenceData.users)) {
                referenceData.users.forEach(u => {
                    html += `<option value="${u.id}">${escapeHtml(u.name)}</option>`;
                });
            }
            ownerSelect.innerHTML = html;
            // Pre-select current user by default
            const currentUserId = window.NEXFLOW_USER?.id || referenceData.current_user?.id;
            if (currentUserId) {
                ownerSelect.value = currentUserId;
            }
        }

        // 5. Mobile Filter Team Member Select
        const mobileOwnerSelect = document.getElementById("mobileFilterOwnerSelect");
        if (mobileOwnerSelect) {
            let html = '<option value="All">All Team Members</option>';
            if (Array.isArray(referenceData.users)) {
                referenceData.users.forEach(u => {
                    html += `<option value="${escapeHtml(u.name)}">${escapeHtml(u.name)}</option>`;
                    ownerFilters[u.name] = true;
                });
            }
            mobileOwnerSelect.innerHTML = html;
        }
    }

    function loadCalendarEvents() {
        return fetch("api/calendar.php?action=events")
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    allEvents = Array.isArray(res.data) ? res.data : (res.data.events || []);
                } else {
                    allEvents = [];
                }
                applyFilterAndRender();
            })
            .catch(err => {
                console.error("Failed to load calendar events:", err);
                allEvents = [];
                applyFilterAndRender();
            });
    }

    // =========================================================================
    // EVENT LISTENERS
    // =========================================================================

    function setupEventListeners() {
        const root = document.querySelector(".calendar-page");
        if (!root) return;

        // Form submit
        const form = document.getElementById("createEventForm");
        if (form) {
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                saveEventFormSubmit();
            });
        }

        // Global Backdrop & ESC
        document.querySelectorAll("#calendarEventDrawer, #calendarFilterDrawer, #calendarContactDrawer").forEach(drawer => {
            drawer.addEventListener("click", function(e) {
                if (e.target === drawer) {
                    if (drawer.id === "calendarEventDrawer") window.calendarApp.closeCreateDrawer();
                    if (drawer.id === "calendarFilterDrawer") window.calendarApp.closeFilterDrawer();
                    if (drawer.id === "calendarContactDrawer") window.calendarApp.closeContactDrawer();
                }
            });
        });

        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                const contactDrawer = document.getElementById("calendarContactDrawer");
                if (contactDrawer && contactDrawer.classList.contains("show")) {
                    window.calendarApp.closeContactDrawer();
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                window.calendarApp.closePopover();
                window.calendarApp.closeCreateDrawer();
                window.calendarApp.closeFilterDrawer();
            }
        });
    }

    // =========================================================================
    // FILTERING & RENDERING
    // =========================================================================

    function applyFilterAndRender() {
        const currentUserId = window.NEXFLOW_USER?.id || referenceData.current_user?.id || 1;

        filteredEvents = allEvents.filter(e => {
            const isTask = (e.event_source === 'task_deadline' || e.is_task);

            // 1. Task Deadline filter
            if (isTask && !calendarFilters.task_deadlines) return false;

            // 2. Sales Activities filter (calendar events)
            if (!isTask && !calendarFilters.sales_activities) return false;

            // 3. My Schedule vs Team Schedule
            const isMyEvent = (parseInt(e.user_id, 10) === parseInt(currentUserId, 10));
            if (isMyEvent && !calendarFilters.my_schedule) return false;
            if (!isMyEvent && !calendarFilters.team_schedule) return false;

            // 4. Event Type filter
            if (eventTypeFilters[e.type] === false) return false;

            // 5. Owner filter
            if (e.owner && ownerFilters[e.owner] === false) return false;

            return true;
        });

        renderHeaderLabel();
        renderMiniCalendar();
        renderUpcomingPanel();

        const container = document.getElementById("calendarMainView");
        if (!container) return;

        if (viewMode === "month") {
            renderMonthView(container);
        } else if (viewMode === "week") {
            renderWeekView(container);
        } else if (viewMode === "day") {
            renderDayView(container);
        } else if (viewMode === "agenda") {
            renderAgendaView(container);
        }
    }

    function updateViewSwitcherButtons() {
        const modes = ["month", "week", "day", "agenda"];
        modes.forEach(m => {
            const btn = document.getElementById(`btnCalView${m.charAt(0).toUpperCase() + m.slice(1)}`);
            if (btn) {
                const isActive = (m === viewMode);
                btn.classList.toggle("active", isActive);
                btn.setAttribute("aria-selected", isActive ? "true" : "false");
            }
        });
    }

    function renderHeaderLabel() {
        const lbl = document.getElementById("calendarPeriodLabel");
        if (!lbl) return;
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        if (viewMode === "month" || viewMode === "agenda") {
            lbl.textContent = `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
        } else if (viewMode === "week") {
            const startOfWeek = getStartOfWeek(currentDate);
            const endOfWeek = new Date(startOfWeek);
            endOfWeek.setDate(endOfWeek.getDate() + 6);
            lbl.textContent = `${monthNames[startOfWeek.getMonth()]} ${startOfWeek.getDate()} – ${monthNames[endOfWeek.getMonth()]} ${endOfWeek.getDate()}, ${currentDate.getFullYear()}`;
        } else if (viewMode === "day") {
            lbl.textContent = `${monthNames[currentDate.getMonth()]} ${currentDate.getDate()}, ${currentDate.getFullYear()}`;
        }
    }

    // Mini Monthly Calendar
    function renderMiniCalendar() {
        const titleEl = document.getElementById("miniCalTitle");
        const gridEl = document.getElementById("miniCalGrid");
        if (!gridEl) return;

        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        if (titleEl) titleEl.textContent = `${monthNames[miniCalDate.getMonth()]} ${miniCalDate.getFullYear()}`;

        let html = `
            <div class="calendar-mini-day-head">M</div>
            <div class="calendar-mini-day-head">T</div>
            <div class="calendar-mini-day-head">W</div>
            <div class="calendar-mini-day-head">T</div>
            <div class="calendar-mini-day-head">F</div>
            <div class="calendar-mini-day-head">S</div>
            <div class="calendar-mini-day-head">S</div>
        `;

        const year = miniCalDate.getFullYear();
        const month = miniCalDate.getMonth();
        const firstDayIndex = (new Date(year, month, 1).getDay() + 6) % 7; // Monday 0
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        // Prev Month Days
        for (let i = firstDayIndex - 1; i >= 0; i--) {
            html += `<div class="calendar-mini-cell other-month">${daysInPrevMonth - i}</div>`;
        }

        // Current Month Days
        const todayStr = formatDateIso(new Date());
        const selectedStr = formatDateIso(selectedDate);

        for (let day = 1; day <= daysInMonth; day++) {
            const cellDateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isToday = cellDateStr === todayStr;
            const isSelected = cellDateStr === selectedStr;

            const hasEvents = filteredEvents.some(e => e.date === cellDateStr);

            html += `
                <div class="calendar-mini-cell ${isToday ? 'today' : ''} ${isSelected ? 'selected' : ''}" onclick="window.calendarApp.selectMiniDate('${cellDateStr}')">
                    ${day}
                    ${hasEvents ? '<span class="calendar-mini-dot"></span>' : ''}
                </div>
            `;
        }

        gridEl.innerHTML = html;
    }

    function renderUpcomingPanel() {
        const container = document.getElementById("calendarUpcomingList");
        if (!container) return;

        const todayIso = formatDateIso(new Date());
        const sorted = [...filteredEvents]
            .filter(e => e.date >= todayIso)
            .sort((a, b) => (a.date + a.startTime).localeCompare(b.date + b.startTime));
        const upcoming = sorted.slice(0, 5);

        if (upcoming.length === 0) {
            container.innerHTML = '<div style="font-size: 11px; color: var(--text-muted); padding: 10px 0;">No upcoming activities.</div>';
            return;
        }

        container.innerHTML = upcoming.map(e => `
            <div class="calendar-upcoming-item" onclick="window.calendarApp.openPopoverFromId('${e.id}', event)">
                <div class="calendar-upcoming-icon" style="background-color: ${e.color}18; color: ${e.color};">${e.typeIcon || '📅'}</div>
                <div style="min-width: 0; flex: 1;">
                    <div style="font-size: 12px; font-weight: 600; color: var(--text-heading); overflow-wrap: anywhere;">${escapeHtml(e.title)}</div>
                    <div style="font-size: 10.5px; color: var(--text-muted);">${formatDateShort(e.date)} • ${escapeHtml(e.startTime)}</div>
                    <div style="font-size: 10.5px; color: var(--text-muted);">${escapeHtml(e.contact || e.company || 'NexFlow CRM')}</div>
                </div>
            </div>
        `).join("");
    }

    // Main Month View Render
    function renderMonthView(container) {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const firstDayIndex = (new Date(year, month, 1).getDay() + 6) % 7; // Monday start
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        let html = `
            <div class="calendar-month-grid">
                <div class="calendar-month-head">Mon</div>
                <div class="calendar-month-head">Tue</div>
                <div class="calendar-month-head">Wed</div>
                <div class="calendar-month-head">Thu</div>
                <div class="calendar-month-head">Fri</div>
                <div class="calendar-month-head">Sat</div>
                <div class="calendar-month-head">Sun</div>
            </div>
            <div class="calendar-month-days">
        `;

        const todayStr = formatDateIso(new Date());

        // Prev Month Cells
        for (let i = firstDayIndex - 1; i >= 0; i--) {
            const dayNum = daysInPrevMonth - i;
            html += `
                <div class="calendar-day-cell other-month">
                    <div class="calendar-day-top"><span class="calendar-day-num">${dayNum}</span></div>
                </div>
            `;
        }

        // Current Month Cells
        for (let day = 1; day <= daysInMonth; day++) {
            const cellDateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isToday = cellDateStr === todayStr;
            const dayEvents = filteredEvents.filter(e => e.date === cellDateStr);

            const displayEvents = dayEvents.slice(0, 3);
            const moreCount = dayEvents.length - 3;

            html += `
                <div class="calendar-day-cell ${isToday ? 'today' : ''}" data-date="${cellDateStr}" ondragover="window.calendarApp.handleDragOver(event)" ondragleave="window.calendarApp.handleDragLeave(event)" ondrop="window.calendarApp.handleDropDate(event, '${cellDateStr}')" onclick="window.calendarApp.handleCellClick('${cellDateStr}', event)">
                    <div class="calendar-day-top">
                        <span class="calendar-day-num">${day}</span>
                    </div>
                    ${displayEvents.map(e => `
                        <div class="calendar-event-chip" style="background-color: ${e.color}1A; color: ${e.color}; border-left: 3px solid ${e.color};" draggable="${(e.event_source === 'task_deadline' || e.is_task) ? 'false' : 'true'}" ondragstart="window.calendarApp.handleDragStart(event, '${e.id}')" ondragend="window.calendarApp.handleDragEnd(event)" onclick="window.calendarApp.openPopoverFromId('${e.id}', event)">
                            <span style="font-size: 10px;">${e.typeIcon || '📅'}</span>
                            <span style="font-weight: 600; font-size: 10.5px;">${escapeHtml(e.startTime.replace(':00', ''))}</span>
                            <span style="overflow: hidden; text-overflow: ellipsis;">${escapeHtml(e.title)}</span>
                        </div>
                    `).join("")}
                    ${moreCount > 0 ? `<button type="button" class="calendar-more-events-btn" onclick="window.calendarApp.openDayViewForDate('${cellDateStr}', event)">+${moreCount} more</button>` : ''}
                </div>
            `;
        }

        // Next Month Padding
        const totalCellsSoFar = firstDayIndex + daysInMonth;
        const totalGridCells = Math.ceil(totalCellsSoFar / 7) * 7;
        for (let day = 1; day <= (totalGridCells - totalCellsSoFar); day++) {
            html += `
                <div class="calendar-day-cell other-month">
                    <div class="calendar-day-top"><span class="calendar-day-num">${day}</span></div>
                </div>
            `;
        }

        html += `</div>`;
        container.innerHTML = html;
    }

    // Week View Render
    function renderWeekView(container) {
        const startOfWeek = getStartOfWeek(currentDate);
        const days = [];
        for (let i = 0; i < 7; i++) {
            const d = new Date(startOfWeek);
            d.setDate(d.getDate() + i);
            days.push(d);
        }

        const todayStr = formatDateIso(new Date());

        let html = `
            <div class="calendar-time-grid-wrapper">
                <div class="calendar-time-header-row">
                    <div class="calendar-time-col-head" style="font-size: 10px;">Time</div>
                    ${days.map(d => {
                        const dateStr = formatDateIso(d);
                        const isToday = dateStr === todayStr;
                        return `
                            <div class="calendar-time-col-head ${isToday ? 'today' : ''}">
                                <div style="font-size: 11px; font-weight: 500; color: var(--text-muted);">${["Mon","Tue","Wed","Thu","Fri","Sat","Sun"][d.getDay() === 0 ? 6 : d.getDay() - 1]}</div>
                                <div style="${isToday ? 'color: var(--primary); font-weight: 700;' : ''}">${d.getDate()}</div>
                            </div>
                        `;
                    }).join("")}
                </div>
                <div class="calendar-time-body-grid">
                    <div style="display: flex; flex-direction: column;">
                        ${Array.from({length: 24}).map((_, h) => `<div class="calendar-hour-label">${formatHourLabel(h)}</div>`).join("")}
                    </div>
                    ${days.map(d => {
                        const dateStr = formatDateIso(d);
                        const dayEvts = filteredEvents.filter(e => e.date === dateStr);
                        return `
                            <div class="calendar-time-column" data-date="${dateStr}" ondragover="window.calendarApp.handleDragOver(event)" ondrop="window.calendarApp.handleDropDate(event, '${dateStr}')">
                                ${Array.from({length: 24}).map((_, h) => `<div class="calendar-time-slot" onclick="window.calendarApp.openCreateDrawerWithSlot('${dateStr}', '${h}')"></div>`).join("")}
                                ${dateStr === todayStr ? '<div class="calendar-current-time-line" style="top: 500px;"></div>' : ''}
                                ${dayEvts.map(e => {
                                    const topPx = (e.startHour !== undefined && e.startHour !== null ? e.startHour : 10) * 48;
                                    return `
                                        <div class="calendar-timed-event-block" style="top: ${topPx}px; height: 46px; background-color: ${e.color};" draggable="${e.event_source === 'task_deadline' ? 'false' : 'true'}" ondragstart="window.calendarApp.handleDragStart(event, '${e.id}')" onclick="window.calendarApp.openPopoverFromId('${e.id}', event)">
                                            <div style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(e.title)}</div>
                                            <div style="font-size: 9.5px; opacity: 0.9;">${escapeHtml(e.startTime)} • ${escapeHtml(e.contact || e.company || '')}</div>
                                        </div>
                                    `;
                                }).join("")}
                            </div>
                        `;
                    }).join("")}
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    // Day View Render
    function renderDayView(container) {
        const dateStr = formatDateIso(currentDate);
        const dayEvts = filteredEvents.filter(e => e.date === dateStr);
        const isToday = dateStr === formatDateIso(new Date());

        let html = `
            <div class="calendar-time-grid-wrapper">
                <div class="calendar-day-view-header-row">
                    <div class="calendar-time-col-head" style="font-size: 10px;">Time</div>
                    <div class="calendar-time-col-head" style="text-align: left; padding-left: 16px;">
                        <span style="font-size: 14px; font-weight: 700; color: var(--text-heading);">${formatDateFull(currentDate)}</span>
                    </div>
                </div>
                <div class="calendar-day-time-body-grid">
                    <div style="display: flex; flex-direction: column;">
                        ${Array.from({length: 24}).map((_, h) => `<div class="calendar-hour-label">${formatHourLabel(h)}</div>`).join("")}
                    </div>
                    <div class="calendar-time-column" style="border-right: none;" data-date="${dateStr}">
                        ${Array.from({length: 24}).map((_, h) => `<div class="calendar-time-slot" onclick="window.calendarApp.openCreateDrawerWithSlot('${dateStr}', '${h}')"></div>`).join("")}
                        ${isToday ? '<div class="calendar-current-time-line" style="top: 500px;"></div>' : ''}
                        ${dayEvts.map(e => {
                            const topPx = (e.startHour !== undefined && e.startHour !== null ? e.startHour : 10) * 48;
                            return `
                                <div class="calendar-timed-event-block" style="top: ${topPx}px; height: 54px; background-color: ${e.color}; font-size: 12px; padding: 6px 10px;" onclick="window.calendarApp.openPopoverFromId('${e.id}', event)">
                                    <div style="font-weight: 700;">${escapeHtml(e.title)} (${escapeHtml(e.startTime)})</div>
                                    <div style="font-size: 11px; opacity: 0.9;">Account: ${escapeHtml(e.company || 'N/A')} • Contact: ${escapeHtml(e.contact || 'N/A')}</div>
                                </div>
                            `;
                        }).join("")}
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    // Agenda View Render
    function renderAgendaView(container) {
        if (filteredEvents.length === 0) {
            container.innerHTML = `
                <div class="tasks-empty-state" style="margin: 20px;">
                    <div style="font-weight: 600; font-size: 14px; color: var(--text-heading); margin-bottom: 4px;">No Scheduled Events</div>
                    <p style="font-size: 12px; color: var(--text-muted); margin: 0 0 12px;">No calendar events match your current filters or date range.</p>
                    <button type="button" class="btn btn-secondary btn-xs" onclick="window.calendarApp.resetFilters()">Clear Filters</button>
                </div>
            `;
            return;
        }

        const sorted = [...filteredEvents].sort((a, b) => (a.date + a.startTime).localeCompare(b.date + b.startTime));
        const groups = { Today: [], Tomorrow: [], "This Week": [], Later: [] };

        const todayObj = new Date();
        const todayStr = formatDateIso(todayObj);
        const tomObj = new Date(todayObj);
        tomObj.setDate(tomObj.getDate() + 1);
        const tomStr = formatDateIso(tomObj);
        const weekEndObj = new Date(todayObj);
        weekEndObj.setDate(weekEndObj.getDate() + 7);
        const weekEndStr = formatDateIso(weekEndObj);

        sorted.forEach(e => {
            if (e.date === todayStr) groups.Today.push(e);
            else if (e.date === tomStr) groups.Tomorrow.push(e);
            else if (e.date > tomStr && e.date <= weekEndStr) groups["This Week"].push(e);
            else groups.Later.push(e);
        });

        let html = `<div class="calendar-agenda-container">`;

        for (const [grpName, items] of Object.entries(groups)) {
            if (items.length === 0) continue;
            html += `
                <div>
                    <div class="calendar-agenda-group-title">${escapeHtml(grpName)} (${items.length})</div>
                    ${items.map(e => `
                        <div class="calendar-agenda-row" onclick="window.calendarApp.openPopoverFromId('${e.id}', event)">
                            <div class="calendar-agenda-left">
                                <div class="calendar-upcoming-icon" style="background-color: ${e.color}18; color: ${e.color}; width: 32px; height: 32px; font-size: 14px;">${e.typeIcon || '📅'}</div>
                                <div style="min-width: 0;">
                                    <div style="font-size: 13.5px; font-weight: 600; color: var(--text-heading); overflow-wrap: anywhere;">${escapeHtml(e.title)}</div>
                                    <div style="font-size: 11px; color: var(--text-muted);">${formatDateShort(e.date)} • ${escapeHtml(e.startTime)} – ${escapeHtml(e.endTime || '')}</div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
                                <div style="text-align: right;">
                                    <div style="font-size: 12px; font-weight: 500; color: var(--text-heading);">${escapeHtml(e.company || 'NexFlow CRM')}</div>
                                    <div style="font-size: 10.5px; color: var(--text-muted);">${escapeHtml(e.contact || e.owner)}</div>
                                </div>
                                <button type="button" class="btn btn-ghost btn-xs" style="color: var(--primary);">Details &rarr;</button>
                            </div>
                        </div>
                    `).join("")}
                </div>
            `;
        }

        html += `</div>`;
        container.innerHTML = html;
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    let selectedContactId = null;
    let activeContactTab = "overview";
    let isCalendarContactDrawerOpen = false;

    window.calendarApp = {
        setViewMode: function(mode) {
            viewMode = mode;
            localStorage.setItem(VIEW_MODE_KEY, mode);
            updateViewSwitcherButtons();
            applyFilterAndRender();
        },

        goToToday: function() {
            currentDate = new Date();
            selectedDate = new Date();
            miniCalDate = new Date();
            applyFilterAndRender();
        },

        navigatePeriod: function(delta) {
            if (viewMode === "month" || viewMode === "agenda") {
                currentDate.setMonth(currentDate.getMonth() + delta);
                miniCalDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            } else if (viewMode === "week") {
                currentDate.setDate(currentDate.getDate() + (delta * 7));
            } else if (viewMode === "day") {
                currentDate.setDate(currentDate.getDate() + delta);
            }
            applyFilterAndRender();
        },

        navigateMiniMonth: function(delta) {
            miniCalDate.setMonth(miniCalDate.getMonth() + delta);
            renderMiniCalendar();
        },

        selectMiniDate: function(dateStr) {
            const parts = dateStr.split("-");
            selectedDate = new Date(parts[0], parseInt(parts[1], 10) - 1, parts[2]);
            currentDate = new Date(selectedDate);
            applyFilterAndRender();
        },

        openDayViewForDate: function(dateStr, e) {
            if (e) e.stopPropagation();
            const parts = dateStr.split("-");
            currentDate = new Date(parts[0], parseInt(parts[1], 10) - 1, parts[2]);
            window.calendarApp.setViewMode("day");
        },

        handleCellClick: function(dateStr, e) {
            if (e.target.closest(".calendar-event-chip") || e.target.closest(".calendar-more-events-btn")) return;
            const parts = dateStr.split("-");
            selectedDate = new Date(parts[0], parseInt(parts[1], 10) - 1, parts[2]);
            renderMiniCalendar();
        },

        toggleCalendarFilter: function(key, isChecked) {
            calendarFilters[key] = isChecked;
            applyFilterAndRender();
        },

        toggleEventTypeFilter: function(type, isChecked) {
            eventTypeFilters[type] = isChecked;
            applyFilterAndRender();
        },

        openPopoverFromId: function(id, e) {
            if (e) e.stopPropagation();
            const evt = allEvents.find(item => String(item.id) === String(id));
            if (!evt) return;

            activePopoverEvent = evt;

            const popover = document.getElementById("calendarEventPopover");
            const badge = document.getElementById("popoverTypeBadge");
            const title = document.getElementById("popoverTitle");
            const time = document.getElementById("popoverTime");
            const company = document.getElementById("popoverCompany");
            const contact = document.getElementById("popoverContact");
            const owner = document.getElementById("popoverOwner");
            const location = document.getElementById("popoverLocation");

            if (badge) {
                badge.textContent = evt.type;
                badge.style.backgroundColor = evt.color;
            }

            const statusBadge = document.getElementById("popoverStatusBadge");
            if (statusBadge) {
                const st = evt.status || "Scheduled";
                statusBadge.textContent = st;
                if (st === "Completed") {
                    statusBadge.style.backgroundColor = "#D1FAE5";
                    statusBadge.style.color = "#065F46";
                } else if (st === "Cancelled") {
                    statusBadge.style.backgroundColor = "#FEE2E2";
                    statusBadge.style.color = "#991B1B";
                } else if (st === "Rescheduled") {
                    statusBadge.style.backgroundColor = "#FEF3C7";
                    statusBadge.style.color = "#92400E";
                } else {
                    statusBadge.style.backgroundColor = "#EFF6FF";
                    statusBadge.style.color = "#1D4ED8";
                }
            }

            if (title) title.textContent = evt.title;
            if (time) time.textContent = `${formatDateShort(evt.date)} • ${evt.startTime} – ${evt.endTime || ''}`;

            const popoverDeal = document.getElementById("popoverDeal");
            const popoverDealRow = document.getElementById("popoverDealRow");
            if (popoverDeal) {
                popoverDeal.textContent = evt.deal || "N/A";
            }
            if (popoverDealRow) {
                popoverDealRow.style.display = evt.deal ? "" : "none";
            }

            if (company) company.textContent = evt.company || "N/A";
            if (contact) contact.textContent = evt.contact || "N/A";
            if (owner) owner.textContent = evt.owner || "Unassigned";
            if (location) location.textContent = evt.location || "N/A";

            // If task deadline, adjust action buttons
            const isTask = (evt.event_source === 'task_deadline' || evt.is_task);
            const deleteBtn = popover ? popover.querySelector("button[onclick*='deleteActiveEvent']") : null;
            if (deleteBtn) {
                deleteBtn.style.display = isTask ? 'none' : '';
            }
            const editBtn = popover ? popover.querySelector("button[onclick*='openEditFromPopover']") : null;
            if (editBtn) {
                editBtn.textContent = isTask ? 'View in Tasks' : 'Edit Event';
            }

            if (popover) {
                if (e && e.clientX) {
                    let left = e.clientX + 10;
                    let top = e.clientY + 10;
                    if (left + 330 > window.innerWidth) left = window.innerWidth - 340;
                    if (top + 260 > window.innerHeight) top = window.innerHeight - 270;
                    popover.style.left = `${Math.max(10, left)}px`;
                    popover.style.top = `${Math.max(10, top)}px`;
                } else {
                    popover.style.left = "50%";
                    popover.style.top = "50%";
                    popover.style.transform = "translate(-50%, -50%)";
                }
                popover.classList.add("show");
            }
        },

        closePopover: function() {
            const popover = document.getElementById("calendarEventPopover");
            if (popover) popover.classList.remove("show");
        },

        openContactFromPopover: function(e) {
            if (e) e.stopPropagation();
            if (!activePopoverEvent) {
                showCalendarToast("No event details selected.");
                return;
            }

            const contactId = activePopoverEvent.contact_id;
            const contactName = activePopoverEvent.contact;

            if (!contactId && (!contactName || contactName === "N/A" || contactName === "Sales Leadership" || contactName === "Internal Alignment")) {
                showCalendarToast("No contact is associated with this event.");
                return;
            }

            window.calendarApp.closePopover();

            if (contactId) {
                fetch(`api/contacts.php?action=get&id=${contactId}`)
                    .then(res => res.json())
                    .then(res => {
                        if (res.success && res.data && res.data.contact) {
                            renderContactDrawer(res.data.contact);
                        } else {
                            renderFallbackContactDrawer(contactName || "Contact Details", activePopoverEvent);
                        }
                    })
                    .catch(err => {
                        console.error("Error loading contact drawer:", err);
                        renderFallbackContactDrawer(contactName || "Contact Details", activePopoverEvent);
                    });
            } else {
                renderFallbackContactDrawer(contactName || "Contact Details", activePopoverEvent);
            }
        },

        switchContactTab: function(tabName, contactObj) {
            activeContactTab = tabName;
            const contact = contactObj || currentDrawerContact;
            if (!contact) return;

            document.querySelectorAll("#calendarContactDrawer .contact-view-tabs .tab-btn").forEach(btn => {
                if (btn.id === `tab-cc-${tabName}`) {
                    btn.classList.add("active");
                    btn.setAttribute("aria-selected", "true");
                } else {
                    btn.classList.remove("active");
                    btn.setAttribute("aria-selected", "false");
                }
            });

            const contentEl = document.getElementById("ccTabContent");
            if (!contentEl) return;

            const compName = contact.primaryCompanyName || contact.company_name || contact.company || "Account";

            if (tabName === "overview") {
                const compsList = Array.isArray(contact.companies) && contact.companies.length > 0
                    ? contact.companies.map(c => escapeHtml(c.name || c.company_name)).join(", ")
                    : compName;

                contentEl.innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div style="background: var(--bg-card); padding: 14px; border-radius: 8px; border: 1px solid var(--border-card);">
                            <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px;">Contact Information</div>
                            <div style="font-size: 13px; color: var(--text-body); margin-bottom: 6px;"><strong>Email:</strong> ${escapeHtml(contact.email || 'N/A')}</div>
                            <div style="font-size: 13px; color: var(--text-body); margin-bottom: 6px;"><strong>Phone:</strong> ${escapeHtml(contact.phone || 'N/A')}</div>
                            <div style="font-size: 13px; color: var(--text-body); margin-bottom: 6px;"><strong>Mobile:</strong> ${escapeHtml(contact.mobile || contact.phone || 'N/A')}</div>
                            <div style="font-size: 13px; color: var(--text-body);"><strong>Location:</strong> ${escapeHtml(contact.city || contact.location || 'N/A')}</div>
                        </div>
                        <div style="background: var(--bg-card); padding: 14px; border-radius: 8px; border: 1px solid var(--border-card);">
                            <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px;">Ownership & Accounts</div>
                            <div style="font-size: 13px; color: var(--text-body); margin-bottom: 6px;"><strong>Contact Owner:</strong> ${escapeHtml(contact.owner_name || contact.owner || 'Unassigned')}</div>
                            <div style="font-size: 13px; color: var(--text-body); margin-bottom: 6px;"><strong>Status:</strong> ${escapeHtml(contact.status || 'Active')}</div>
                            <div style="font-size: 13px; color: var(--text-body);"><strong>Associated Accounts:</strong> ${compsList}</div>
                        </div>
                    </div>
                    <div style="background: var(--bg-card); padding: 14px; border-radius: 8px; border: 1px solid var(--border-card); margin-bottom: 16px;">
                        <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px;">Deals Overview</div>
                        <div style="font-size: 13px; font-weight: 600; color: var(--primary);">${contact.deals_count ? contact.deals_count + ' Deal(s)' : 'No open deals'}</div>
                    </div>
                    <div style="background: var(--bg-card); padding: 14px; border-radius: 8px; border: 1px solid var(--border-card);">
                        <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px;">Notes & Observations</div>
                        <div style="font-size: 13px; color: var(--text-body); line-height: 1.5;">${escapeHtml(contact.notes || contact.description || 'No notes on file.')}</div>
                    </div>
                `;
            } else if (tabName === "activity") {
                contentEl.innerHTML = `
                    <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 12px;">Recent Touchpoints</div>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="padding: 12px; border-radius: 8px; border: 1px solid var(--border-card); background: var(--bg-card);">
                            <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                                <strong style="color: var(--text-heading);">📅 Calendar Interaction</strong>
                                <span style="color: var(--text-muted);">Recent</span>
                            </div>
                            <div style="font-size: 12.5px; color: var(--text-body);">Associated with current calendar event and scheduled sales meetings.</div>
                        </div>
                    </div>
                `;
            } else if (tabName === "deals") {
                contentEl.innerHTML = `
                    <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 12px;">Associated Deals</div>
                    <div style="padding: 14px; border-radius: 8px; border: 1px solid var(--border-card); background: var(--bg-card);">
                        <div style="font-size: 13px; color: var(--text-muted);">Manage sales pipelines and value forecasts in the Pipeline module.</div>
                    </div>
                `;
            } else if (tabName === "tasks") {
                contentEl.innerHTML = `
                    <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 12px;">Pending Action Items</div>
                    <div style="padding: 14px; border-radius: 8px; border: 1px solid var(--border-card); background: var(--bg-card);">
                        <div style="font-size: 13px; color: var(--text-muted);">Track and complete tasks directly from the Tasks management page.</div>
                    </div>
                `;
            } else if (tabName === "notes") {
                contentEl.innerHTML = `
                    <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 12px;">Notes</div>
                    <div style="padding: 14px; border-radius: 8px; border: 1px solid var(--border-card); background: var(--bg-card); font-size: 13px; color: var(--text-body); line-height: 1.5;">
                        ${escapeHtml(contact.notes || contact.description || 'No additional notes.')}
                    </div>
                `;
            }
        },

        closeContactDrawer: function() {
            const drawer = document.getElementById("calendarContactDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
            }
            isCalendarContactDrawerOpen = false;
            document.body.style.overflow = "";

            const contactBtn = document.getElementById("popoverContactBtn");
            if (contactBtn && typeof contactBtn.focus === "function") {
                contactBtn.focus();
            } else if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
                lastFocusedElement.focus();
            }
        },

        handleDragStart: function(e, evtId) {
            const evt = allEvents.find(item => String(item.id) === String(evtId));
            if (!evt || evt.event_source === 'task_deadline' || evt.is_task) {
                e.preventDefault();
                showCalendarToast("Task deadlines cannot be rescheduled from Calendar.");
                return false;
            }
            draggedCalendarEventId = String(evtId);
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = "move";
                try {
                    e.dataTransfer.setData("text/plain", String(evtId));
                } catch (err) {}
            }
            const chip = e.target.closest ? e.target.closest('.calendar-event-chip') : e.target;
            if (chip && chip.classList) {
                chip.classList.add("dragging");
            }
        },

        handleDragOver: function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.dataTransfer) {
                e.dataTransfer.dropEffect = "move";
            }
            const cell = e.target.closest ? e.target.closest('.calendar-day-cell') : null;
            if (cell && !cell.classList.contains('drag-over')) {
                document.querySelectorAll('.calendar-day-cell.drag-over').forEach(c => c.classList.remove('drag-over'));
                cell.classList.add('drag-over');
            }
        },

        handleDragLeave: function(e) {
            const cell = e.target.closest ? e.target.closest('.calendar-day-cell') : null;
            if (cell) {
                cell.classList.remove('drag-over');
            }
        },

        handleDragEnd: function(e) {
            document.querySelectorAll('.calendar-day-cell.drag-over').forEach(c => c.classList.remove('drag-over'));
            document.querySelectorAll('.calendar-event-chip.dragging').forEach(c => c.classList.remove('dragging'));
            draggedCalendarEventId = null;
        },

        handleDropDate: function(e, targetDateStr) {
            e.preventDefault();
            e.stopPropagation();
            document.querySelectorAll('.calendar-day-cell.drag-over').forEach(c => c.classList.remove('drag-over'));
            document.querySelectorAll('.calendar-event-chip.dragging').forEach(c => c.classList.remove('dragging'));

            let evtId = "";
            try {
                evtId = e.dataTransfer.getData("text/plain");
            } catch (err) {}
            if (!evtId && draggedCalendarEventId) {
                evtId = draggedCalendarEventId;
            }
            draggedCalendarEventId = null;

            if (!evtId) return;

            const evt = allEvents.find(item => String(item.id) === String(evtId));
            if (!evt) return;

            if (evt.event_source === 'task_deadline' || evt.is_task) {
                showAlertModal({
                    title: "Task Deadline",
                    message: "Task deadlines are managed within the Tasks module and cannot be rescheduled from the Calendar.",
                    type: "warning"
                });
                return;
            }

            if (evt.date === targetDateStr) {
                return; // Dropped on the same day, no-op
            }

            const previousDate = evt.date;

            // Optimistically update visual date
            evt.date = targetDateStr;
            applyFilterAndRender();

            fetch("api/calendar.php?action=reschedule", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ id: evt.id, date: targetDateStr })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showCalendarToast(`Event rescheduled to ${targetDateStr}.`);
                    loadCalendarEvents();
                } else {
                    evt.date = previousDate;
                    applyFilterAndRender();
                    showAlertModal({
                        title: "Reschedule Failed",
                        message: res.message || "Failed to reschedule event.",
                        type: "danger"
                    });
                }
            })
            .catch(err => {
                console.error("Reschedule failed:", err);
                evt.date = previousDate;
                applyFilterAndRender();
                showAlertModal({
                    title: "Network Error",
                    message: "A network error occurred while updating the event date. The change has been reverted.",
                    type: "danger"
                });
            });
        },

        openCreateDrawer: function() {
            lastFocusedElement = document.activeElement;
            const form = document.getElementById("createEventForm");
            if (form) form.reset();
            document.getElementById("editEventId").value = "";
            document.getElementById("createEventModalTitle").textContent = "New Event";
            document.getElementById("btnSaveEventSubmit").textContent = "Save Event";

            const dateInput = document.getElementById("addEvtDate");
            if (dateInput) dateInput.value = formatDateIso(new Date());

            const ownerSelect = document.getElementById("addEvtOwner");
            if (ownerSelect && referenceData.current_user) {
                ownerSelect.value = referenceData.current_user.id;
            }

            const clientVisCheck = document.getElementById("addEvtClientVisible");
            if (clientVisCheck) clientVisCheck.checked = false;

            const drawer = document.getElementById("calendarEventDrawer");
            if (drawer) {
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
                const firstInput = document.getElementById("addEvtTitle");
                if (firstInput) firstInput.focus();
            }

            document.body.style.overflow = "hidden";
        },

        openCreateDrawerWithSlot: function(dateStr, hour) {
            window.calendarApp.openCreateDrawer();
            const dateInput = document.getElementById("addEvtDate");
            const startTimeInput = document.getElementById("addEvtStartTime");
            const endTimeInput = document.getElementById("addEvtEndTime");

            if (dateInput) dateInput.value = dateStr;
            const hNum = parseInt(hour, 10);
            if (startTimeInput) startTimeInput.value = formatHourLabel(hNum);
            if (endTimeInput) endTimeInput.value = formatHourLabel((hNum + 1) % 24);
        },

        openEditFromPopover: function() {
            if (!activePopoverEvent) return;
            const evt = activePopoverEvent;

            // If task deadline, guide user to Tasks module
            if (evt.event_source === 'task_deadline' || evt.is_task) {
                window.location.href = 'tasks.php';
                return;
            }

            window.calendarApp.closePopover();

            document.getElementById("editEventId").value = evt.id;
            document.getElementById("createEventModalTitle").textContent = "Edit Event";
            document.getElementById("btnSaveEventSubmit").textContent = "Update Event";

            document.getElementById("addEvtTitle").value = evt.title || "";
            setSelectCaseInsensitive(document.getElementById("addEvtType"), evt.type || "Product Demo");
            setSelectCaseInsensitive(document.getElementById("addEvtStatus"), evt.status || "Scheduled");
            document.getElementById("addEvtDate").value = evt.date || formatDateIso(new Date());
            document.getElementById("addEvtStartTime").value = evt.startTime || "10:00 AM";
            document.getElementById("addEvtEndTime").value = evt.endTime || "11:00 AM";
            document.getElementById("addEvtContact").value = evt.contact_id || "";
            document.getElementById("addEvtCompany").value = evt.company_id || "";
            document.getElementById("addEvtDeal").value = evt.deal_id || "";
            document.getElementById("addEvtOwner").value = evt.owner_id || evt.user_id || "";
            document.getElementById("addEvtLocation").value = evt.location || "";
            document.getElementById("addEvtDesc").value = evt.description || "";
            const clientVisCheck = document.getElementById("addEvtClientVisible");
            if (clientVisCheck) {
                clientVisCheck.checked = (evt.client_visible === 1 || evt.client_visible === '1' || evt.client_visible === true);
            }

            const drawer = document.getElementById("calendarEventDrawer");
            if (drawer) {
                drawer.classList.add("show");
                drawer.setAttribute("aria-hidden", "false");
                const firstInput = document.getElementById("addEvtTitle");
                if (firstInput) firstInput.focus();
            }

            document.body.style.overflow = "hidden";
        },

        closeCreateDrawer: function() {
            const drawer = document.getElementById("calendarEventDrawer");
            if (drawer) {
                drawer.classList.remove("show");
                drawer.setAttribute("aria-hidden", "true");
            }
            document.body.style.overflow = "";
            restoreKeyboardFocus();
        },

        deleteActiveEvent: function() {
            if (!activePopoverEvent) return;
            const evt = activePopoverEvent;

            if (evt.event_source === 'task_deadline' || evt.is_task) {
                showAlertModal({
                    title: "Task Deadline",
                    message: "Task deadlines are managed within the Tasks module and cannot be deleted from the Calendar. Please delete or mark the task complete in Tasks.",
                    type: "warning"
                });
                return;
            }

            window.calendarApp.closePopover();

            showConfirmModal({
                title: "Delete Event",
                message: `Are you sure you want to delete '${evt.title}'?`,
                type: "danger",
                confirmText: "Delete Event",
                onConfirm: function() {
                    fetch("api/calendar.php?action=delete", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ id: evt.id })
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            showCalendarToast("Event deleted successfully.");
                            loadCalendarEvents();
                        } else {
                            showAlertModal({ title: "Delete Failed", message: res.message || "Failed to delete event.", type: "danger" });
                        }
                    })
                    .catch(err => {
                        console.error("Delete failed:", err);
                        showAlertModal({ title: "Error", message: "Network error while deleting event.", type: "danger" });
                    });
                }
            });
        },

        openFilterDrawer: function() {
            lastFocusedElement = document.activeElement;
            const panel = document.getElementById("calendarFilterDrawer");
            if (panel) {
                panel.classList.add("show");
                panel.setAttribute("aria-hidden", "false");
            }
            document.body.style.overflow = "hidden";
        },

        closeFilterDrawer: function() {
            const panel = document.getElementById("calendarFilterDrawer");
            if (panel) {
                panel.classList.remove("show");
                panel.setAttribute("aria-hidden", "true");
            }
            document.body.style.overflow = "";
            restoreKeyboardFocus();
        },

        applyMobileFilterDrawer: function() {
            const type = document.getElementById("mobileFilterTypeSelect").value;
            const owner = document.getElementById("mobileFilterOwnerSelect").value;

            if (type !== "All") {
                Object.keys(eventTypeFilters).forEach(k => eventTypeFilters[k] = (k === type));
            }
            if (owner !== "All") {
                Object.keys(ownerFilters).forEach(k => ownerFilters[k] = (k === owner));
            }

            window.calendarApp.closeFilterDrawer();
            applyFilterAndRender();
        },

        resetFilters: function() {
            Object.keys(calendarFilters).forEach(k => calendarFilters[k] = true);
            Object.keys(eventTypeFilters).forEach(k => eventTypeFilters[k] = true);
            Object.keys(ownerFilters).forEach(k => ownerFilters[k] = true);

            document.querySelectorAll(".calendar-checkbox-list input[type='checkbox']").forEach(cb => cb.checked = true);

            window.calendarApp.closeFilterDrawer();
            applyFilterAndRender();
        },

        handleContactChange: function(contactId) {
            if (!contactId) return;
            const cid = parseInt(contactId, 10);
            const foundContact = referenceData.contacts.find(c => parseInt(c.id, 10) === cid);
            if (foundContact && Array.isArray(foundContact.companies) && foundContact.companies.length > 0) {
                const compSelect = document.getElementById("addEvtCompany");
                if (compSelect) {
                    compSelect.value = foundContact.companies[0].company_id;
                }
            }
        },

        handleDealChange: function(dealId) {
            if (!dealId) return;
            const did = parseInt(dealId, 10);
            const foundDeal = referenceData.deals.find(d => parseInt(d.id, 10) === did);
            if (foundDeal) {
                if (foundDeal.contact_id) {
                    const contactSelect = document.getElementById("addEvtContact");
                    if (contactSelect) contactSelect.value = foundDeal.contact_id;
                }
                if (foundDeal.company) {
                    const compSelect = document.getElementById("addEvtCompany");
                    if (compSelect) {
                        for (let i = 0; i < compSelect.options.length; i++) {
                            if (compSelect.options[i].text.toLowerCase() === foundDeal.company.toLowerCase()) {
                                compSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                }
            }
        },

        reloadEvents: function() {
            loadCalendarEvents();
        }
    };

    // =========================================================================
    // FORM SUBMIT (CREATE / UPDATE)
    // =========================================================================

    function parseTimeToMinutes(timeStr) {
        if (!timeStr) return null;
        const s = timeStr.trim();
        const match = s.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/i);
        if (match) {
            let hours = parseInt(match[1], 10);
            const minutes = parseInt(match[2], 10);
            const meridiem = match[3] ? match[3].toUpperCase() : null;
            if (meridiem) {
                if (meridiem === "PM" && hours < 12) hours += 12;
                if (meridiem === "AM" && hours === 12) hours = 0;
            }
            if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) return null;
            return hours * 60 + minutes;
        }
        const d = new Date(`1970-01-01T${s}`);
        if (!isNaN(d.getTime())) {
            return d.getHours() * 60 + d.getMinutes();
        }
        return null;
    }

    function setSelectCaseInsensitive(selectEl, value) {
        if (!selectEl) return;
        const target = (value || "").toLowerCase().trim();
        for (let i = 0; i < selectEl.options.length; i++) {
            const optVal = selectEl.options[i].value.toLowerCase().trim();
            const optText = selectEl.options[i].text.toLowerCase().trim();
            if (optVal === target || optText === target) {
                selectEl.selectedIndex = i;
                return;
            }
        }
        selectEl.value = value;
    }

    function saveEventFormSubmit() {
        const editId = document.getElementById("editEventId") ? document.getElementById("editEventId").value : "";
        const title = document.getElementById("addEvtTitle") ? document.getElementById("addEvtTitle").value.trim() : "";
        const type = document.getElementById("addEvtType") ? document.getElementById("addEvtType").value : "Product Demo";
        const status = document.getElementById("addEvtStatus") ? document.getElementById("addEvtStatus").value : "Scheduled";
        const date = document.getElementById("addEvtDate") ? document.getElementById("addEvtDate").value : formatDateIso(new Date());
        const startTime = document.getElementById("addEvtStartTime") ? document.getElementById("addEvtStartTime").value.trim() : "10:00 AM";
        const endTime = document.getElementById("addEvtEndTime") ? document.getElementById("addEvtEndTime").value.trim() : "11:00 AM";
        const contactId = document.getElementById("addEvtContact") ? document.getElementById("addEvtContact").value : "";
        const companyId = document.getElementById("addEvtCompany") ? document.getElementById("addEvtCompany").value : "";
        const dealId = document.getElementById("addEvtDeal") ? document.getElementById("addEvtDeal").value : "";
        const ownerId = document.getElementById("addEvtOwner") ? document.getElementById("addEvtOwner").value : "";
        const location = document.getElementById("addEvtLocation") ? document.getElementById("addEvtLocation").value.trim() : "";
        const desc = document.getElementById("addEvtDesc") ? document.getElementById("addEvtDesc").value.trim() : "";

        // 1. Validate Title
        if (!title) {
            showAlertModal({ title: "Validation Required", message: "Event title is required.", type: "warning" });
            return;
        }

        // 2. Validate Date
        if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
            showAlertModal({ title: "Validation Required", message: "Please enter a valid event date (YYYY-MM-DD).", type: "warning" });
            return;
        }

        // 3. Validate Start Time
        if (!startTime) {
            showAlertModal({ title: "Validation Required", message: "Start time is required.", type: "warning" });
            return;
        }

        // 4. Validate End Time
        if (!endTime) {
            showAlertModal({ title: "Validation Required", message: "End time is required.", type: "warning" });
            return;
        }

        // 5. Strictly Validate End Time > Start Time
        const startMin = parseTimeToMinutes(startTime);
        const endMin = parseTimeToMinutes(endTime);

        if (startMin === null) {
            showAlertModal({ title: "Validation Required", message: "Please enter a valid start time.", type: "warning" });
            return;
        }

        if (endMin === null) {
            showAlertModal({ title: "Validation Required", message: "Please enter a valid end time.", type: "warning" });
            return;
        }

        if (endMin <= startMin) {
            showAlertModal({ title: "Invalid Time", message: "End time must be later than start time.", type: "warning" });
            return;
        }

        const payload = {
            id: editId || undefined,
            title: title,
            event_type: type,
            status: status,
            date: date,
            start_time: startTime,
            end_time: endTime,
            contact_id: contactId ? parseInt(contactId, 10) : null,
            company_id: companyId ? parseInt(companyId, 10) : null,
            deal_id: dealId ? parseInt(dealId, 10) : null,
            user_id: ownerId ? parseInt(ownerId, 10) : null,
            location: location,
            description: desc,
            client_visible: document.getElementById("addEvtClientVisible") ? (document.getElementById("addEvtClientVisible").checked ? 1 : 0) : 0
        };

        const action = editId ? "update" : "create";
        const saveBtn = document.getElementById("btnSaveEventSubmit");
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = "Saving...";
        }

        fetch(`api/calendar.php?action=${action}`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(res => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = editId ? "Update Event" : "Save Event";
            }
            if (res.success) {
                window.calendarApp.closeCreateDrawer();
                document.getElementById("createEventForm").reset();
                showCalendarToast(editId ? "Event updated successfully!" : `Event '${title}' created successfully!`);
                loadCalendarEvents();
            } else {
                showAlertModal({ title: "Save Failed", message: res.message || "Failed to save event.", type: "danger" });
            }
        })
        .catch(err => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = editId ? "Update Event" : "Save Event";
            }
            console.error("Save event failed:", err);
            showAlertModal({ title: "Error", message: "A network error occurred while saving the event.", type: "danger" });
        });
    }

    // =========================================================================
    // CONTACT DRAWER HELPERS
    // =========================================================================

    function renderContactDrawer(contact) {
        currentDrawerContact = contact;
        selectedContactId = contact.id;
        activeContactTab = "overview";
        isCalendarContactDrawerOpen = true;

        const breadcrumbEl = document.getElementById("ccBreadcrumbName");
        const nameEl = document.getElementById("ccName");
        const avatarEl = document.getElementById("ccAvatar");
        const titleCompEl = document.getElementById("ccTitleCompany");
        const relBadgeEl = document.getElementById("ccRelBadge");

        const initials = contact.name.split(" ").map(n => n[0]).join("").toUpperCase().substring(0, 2) || "CT";
        const companyName = contact.primaryCompanyName || contact.company_name || "Account";

        if (breadcrumbEl) breadcrumbEl.textContent = `Contact Details • ${contact.name}`;
        if (nameEl) nameEl.textContent = contact.name;
        if (avatarEl) {
            avatarEl.textContent = initials;
            avatarEl.style.backgroundColor = contact.avatar_color || "#7C3AED";
        }
        if (titleCompEl) titleCompEl.textContent = `${contact.title || 'Representative'} • ${companyName}`;
        if (relBadgeEl) {
            relBadgeEl.textContent = contact.status || "Active";
            relBadgeEl.className = `status-badge status-customer`;
        }

        window.calendarApp.switchContactTab("overview", contact);

        const drawer = document.getElementById("calendarContactDrawer");
        if (drawer) {
            drawer.classList.add("show");
            drawer.setAttribute("aria-hidden", "false");
        }
        document.body.style.overflow = "hidden";

        const closeBtn = document.getElementById("btnCloseContactDrawer");
        if (closeBtn) closeBtn.focus();
    }

    function renderFallbackContactDrawer(name, evt) {
        const fallback = {
            id: evt.contact_id || 0,
            name: name,
            title: "Contact",
            company_name: evt.company || "Company",
            email: "N/A",
            phone: "N/A",
            status: "Active",
            owner_name: evt.owner || "Team Member",
            notes: `Associated with event '${evt.title}'.`
        };
        renderContactDrawer(fallback);
    }

    // =========================================================================
    // UTILITY HELPERS
    // =========================================================================

    function restoreKeyboardFocus() {
        if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
            lastFocusedElement.focus();
            lastFocusedElement = null;
        }
    }

    function getStartOfWeek(d) {
        const date = new Date(d);
        const day = date.getDay();
        const diff = date.getDate() - day + (day === 0 ? -6 : 1); // Monday start
        return new Date(date.setDate(diff));
    }

    function formatDateIso(d) {
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }

    function formatDateShort(dateStr) {
        if (!dateStr) return "";
        const parts = dateStr.split("-");
        const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        return `${monthNames[parseInt(parts[1], 10) - 1]} ${parseInt(parts[2], 10)}`;
    }

    function formatDateFull(d) {
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        const dayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
        return `${dayNames[d.getDay()]}, ${monthNames[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`;
    }

    function formatHourLabel(h) {
        if (h === 0) return "12 AM";
        if (h < 12) return `${h} AM`;
        if (h === 12) return "12 PM";
        return `${h - 12} PM`;
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    window.showCalendarToast = function(msg) {
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
