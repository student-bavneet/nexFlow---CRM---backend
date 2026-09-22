<?php
// Sales Calendar Management Page
$page_title = "Calendar";
$current_page = "calendar";
$page_script = "calendar.js";

include __DIR__ . '/includes/header.php';
requirePermission('calendar');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';

$currentUserData = [
    'id'              => (int)($_SESSION['user_id'] ?? 1),
    'name'            => (string)($_SESSION['user_name'] ?? 'User'),
    'organization_id' => (int)($_SESSION['organization_id'] ?? 1)
];
?>

<script>
    window.NEXFLOW_USER = <?php echo json_encode($currentUserData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
</script>

<main class="main-content calendar-page">
    <div class="page-container">
        <!-- Page Header -->
        <div class="calendar-header">
            <div class="calendar-header-left">
                <div class="calendar-title-row">
                    <h1 class="calendar-header-title">Calendar</h1>
                    <span class="calendar-period-badge" id="calendarPeriodLabel"><?php echo date('F Y'); ?></span>
                </div>
                <p class="calendar-header-subtitle">Schedule calls, meetings, demos and sales follow-ups.</p>
            </div>
            <div class="calendar-header-right">
                <div class="calendar-nav-group">
                    <button type="button" class="calendar-nav-btn" onclick="window.calendarApp.goToToday()">Today</button>
                    <button type="button" class="calendar-nav-btn" title="Previous Period" onclick="window.calendarApp.navigatePeriod(-1)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <button type="button" class="calendar-nav-btn" title="Next Period" onclick="window.calendarApp.navigatePeriod(1)">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>

                <button type="button" class="btn btn-primary btn-sm" onclick="window.calendarApp.openCreateDrawer()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    New Event
                </button>
            </div>
        </div>

        <!-- Toolbar & View Switcher -->
        <div class="calendar-toolbar">
            <div class="calendar-toolbar-left">
                <div class="calendar-segmented-control" role="tablist" aria-label="Calendar View Modes">
                    <button type="button" class="calendar-segmented-btn active" id="btnCalViewMonth" role="tab" aria-selected="true" onclick="window.calendarApp.setViewMode('month')">Month</button>
                    <button type="button" class="calendar-segmented-btn" id="btnCalViewWeek" role="tab" aria-selected="false" onclick="window.calendarApp.setViewMode('week')">Week</button>
                    <button type="button" class="calendar-segmented-btn" id="btnCalViewDay" role="tab" aria-selected="false" onclick="window.calendarApp.setViewMode('day')">Day</button>
                    <button type="button" class="calendar-segmented-btn" id="btnCalViewAgenda" role="tab" aria-selected="false" onclick="window.calendarApp.setViewMode('agenda')">Agenda</button>
                </div>

                <button type="button" class="btn btn-secondary btn-sm" onclick="window.calendarApp.openFilterDrawer()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filters
                </button>
            </div>

            <div class="calendar-toolbar-right">
                <span style="font-size: 11px; color: var(--text-muted); display: flex; align-items: center; gap: 4px;">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    Integration available in a future backend phase
                </span>
            </div>
        </div>

        <!-- Two-Part Calendar Layout -->
        <div class="calendar-layout">
            <!-- Left Utility Panel (Desktop) -->
            <aside class="calendar-sidebar">
                <!-- Mini Monthly Calendar Card -->
                <div class="calendar-card">
                    <div class="calendar-mini-header">
                        <span class="calendar-mini-title" id="miniCalTitle"><?php echo date('F Y'); ?></span>
                        <div style="display: flex; align-items: center; gap: 2px;">
                            <button type="button" class="btn btn-ghost btn-xs" style="padding: 2px 4px;" onclick="window.calendarApp.navigateMiniMonth(-1)">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                            </button>
                            <button type="button" class="btn btn-ghost btn-xs" style="padding: 2px 4px;" onclick="window.calendarApp.navigateMiniMonth(1)">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="calendar-mini-grid" id="miniCalGrid">
                        <!-- Rendered by JS -->
                    </div>
                </div>

                <!-- My Calendars Filter Card -->
                <div class="calendar-card">
                    <div class="calendar-filter-title">My Calendars</div>
                    <div class="calendar-checkbox-list">
                        <label class="calendar-checkbox-label">
                            <input type="checkbox" checked onchange="window.calendarApp.toggleCalendarFilter('my_schedule', this.checked)">
                            <span>My Schedule</span>
                        </label>
                        <label class="calendar-checkbox-label">
                            <input type="checkbox" checked onchange="window.calendarApp.toggleCalendarFilter('team_schedule', this.checked)">
                            <span>Team Schedule</span>
                        </label>
                        <label class="calendar-checkbox-label">
                            <input type="checkbox" checked onchange="window.calendarApp.toggleCalendarFilter('sales_activities', this.checked)">
                            <span>Sales Activities</span>
                        </label>
                        <label class="calendar-checkbox-label">
                            <input type="checkbox" checked onchange="window.calendarApp.toggleCalendarFilter('task_deadlines', this.checked)">
                            <span>Task Deadlines</span>
                        </label>
                    </div>
                </div>

                <!-- Event Type Filter Card -->
                <div class="calendar-card">
                    <div class="calendar-filter-title">Event Types</div>
                    <div class="calendar-checkbox-list">
                        <label class="calendar-checkbox-label">
                            <input type="checkbox" checked onchange="window.calendarApp.toggleEventTypeFilter('Call', this.checked)">
                            <span style="display: flex; align-items: center; gap: 6px;"><span style="color: #059669;">📞</span> Call</span>
                        </label>
                        <label class="calendar-checkbox-label">
                            <input type="checkbox" checked onchange="window.calendarApp.toggleEventTypeFilter('Meeting', this.checked)">
                            <span style="display: flex; align-items: center; gap: 6px;"><span style="color: #2563EB;">📅</span> Meeting</span>
                        </label>
                        <label class="calendar-checkbox-label">
                            <input type="checkbox" checked onchange="window.calendarApp.toggleEventTypeFilter('Product Demo', this.checked)">
                            <span style="display: flex; align-items: center; gap: 6px;"><span style="color: #7C3AED;">💻</span> Product Demo</span>
                        </label>
                        <label class="calendar-checkbox-label">
                            <input type="checkbox" checked onchange="window.calendarApp.toggleEventTypeFilter('Follow-up', this.checked)">
                            <span style="display: flex; align-items: center; gap: 6px;"><span style="color: #F79009;">🔔</span> Follow-up</span>
                        </label>
                        <label class="calendar-checkbox-label">
                            <input type="checkbox" checked onchange="window.calendarApp.toggleEventTypeFilter('Proposal', this.checked)">
                            <span style="display: flex; align-items: center; gap: 6px;"><span style="color: #4F46E5;">📝</span> Proposal</span>
                        </label>
                        <label class="calendar-checkbox-label">
                            <input type="checkbox" checked onchange="window.calendarApp.toggleEventTypeFilter('Task Deadline', this.checked)">
                            <span style="display: flex; align-items: center; gap: 6px;"><span style="color: #D92D20;">📋</span> Task Deadline</span>
                        </label>
                    </div>
                </div>

                <!-- Next 5 Upcoming Activities Panel -->
                <div class="calendar-card">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span class="calendar-filter-title" style="margin: 0;">Upcoming Activities</span>
                        <button type="button" class="btn btn-ghost btn-xs" style="font-size: 11px; padding: 0; color: var(--primary);" onclick="window.calendarApp.setViewMode('agenda')">View all</button>
                    </div>
                    <div id="calendarUpcomingList">
                        <!-- Rendered dynamically by JS -->
                    </div>
                </div>
            </aside>

            <!-- Main Calendar View Area -->
            <div class="calendar-main" id="calendarMainView">
                <!-- Rendered dynamically by JS (Month / Week / Day / Agenda) -->
            </div>
        </div>
    </div>
</main>

<!-- Desktop Event Anchored Popover -->
<div class="calendar-event-popover" id="calendarEventPopover">
    <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 8px;">
        <div style="display: flex; align-items: center; gap: 6px;">
            <span class="calendar-event-chip" id="popoverTypeBadge" style="font-weight: 600; color: #FFFFFF;">Event</span>
            <span class="status-badge" id="popoverStatusBadge" style="font-size: 10.5px; padding: 2px 7px; border-radius: 999px; font-weight: 500;">Scheduled</span>
        </div>
        <button type="button" class="btn btn-ghost btn-xs" onclick="window.calendarApp.closePopover()" style="padding: 2px;">&times;</button>
    </div>
    <h4 style="font-size: 14px; font-weight: 700; color: var(--text-heading); margin: 0 0 4px;" id="popoverTitle">Event Title</h4>
    <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 10px;" id="popoverTime">—</div>

    <div style="font-size: 12px; color: var(--text-body); display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px;">
        <div id="popoverDealRow" style="display: none;"><strong>Deal:</strong> <span id="popoverDeal" style="color: var(--primary);">—</span></div>
        <div><strong>Account:</strong> <span id="popoverCompany">—</span></div>
        <div><strong>Contact:</strong> <span id="popoverContact" style="color: var(--primary);">—</span></div>
        <div><strong>Owner:</strong> <span id="popoverOwner">—</span></div>
        <div><strong>Location:</strong> <span id="popoverLocation" style="overflow-wrap: anywhere;">—</span></div>
    </div>

    <div style="display: flex; align-items: center; justify-content: space-between; padding-top:10px; border-top: 1px solid var(--border-divider);">
        <button type="button" class="btn btn-secondary btn-xs" id="popoverContactBtn" onclick="window.calendarApp.openContactFromPopover(event)">Open Contact</button>
        <div style="display: flex; align-items: center; gap: 4px;">
            <button type="button" class="btn btn-ghost btn-xs" style="color: #F04438;" onclick="window.calendarApp.deleteActiveEvent()">Delete</button>
            <button type="button" class="btn btn-primary btn-xs" onclick="window.calendarApp.openEditFromPopover()">Edit Event</button>
        </div>
    </div>
</div>

<!-- Drawer 1: Create / Edit Event Drawer (460px wide) -->
<div class="calendar-event-drawer" id="calendarEventDrawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="createEventModalTitle">
    <div class="calendar-event-drawer-panel" aria-hidden="true">
        <div class="calendar-drawer-header">
            <h3 style="font-size: 16px; font-weight: 600; color: var(--text-heading); margin: 0;" id="createEventModalTitle">New Event</h3>
            <button type="button" class="btn btn-ghost btn-xs" onclick="window.calendarApp.closeCreateDrawer()" style="padding: 4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="createEventForm" style="display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; height: 100%; max-height: 100%; overflow: hidden;">
            <input type="hidden" id="editEventId" value="">
            <div class="calendar-drawer-body">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Event Details</div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Event Title *</label>
                    <input type="text" class="input-control input-sm" id="addEvtTitle" required placeholder="e.g., Demo call with Daniel Reyes">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Event Type</label>
                        <select class="input-control input-sm" id="addEvtType">
                            <option value="Call">Call</option>
                            <option value="Meeting">Meeting</option>
                            <option value="Product Demo" selected>Product Demo</option>
                            <option value="Follow-up">Follow-up</option>
                            <option value="Proposal">Proposal</option>
                            <option value="Task Deadline">Task Deadline</option>
                            <option value="Team Event">Team Event</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select class="input-control input-sm" id="addEvtStatus">
                            <option value="Scheduled" selected>Scheduled</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Rescheduled">Rescheduled</option>
                            <option value="No Show">No Show</option>
                        </select>
                    </div>
                </div>

                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Schedule</div>
                <div style="display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 8px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Date *</label>
                        <input type="date" class="input-control input-sm" id="addEvtDate" required>
                    </div>
                    <div>
                        <label class="form-label">Start Time *</label>
                        <input type="text" class="input-control input-sm" id="addEvtStartTime" value="10:00 AM" required placeholder="e.g. 10:00 AM">
                    </div>
                    <div>
                        <label class="form-label">End Time *</label>
                        <input type="text" class="input-control input-sm" id="addEvtEndTime" value="11:00 AM" required placeholder="e.g. 11:00 AM">
                    </div>
                </div>

                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Associations & Assignment</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Related Contact</label>
                        <select class="input-control input-sm" id="addEvtContact" onchange="window.calendarApp.handleContactChange(this.value)">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Related Company</label>
                        <select class="input-control input-sm" id="addEvtCompany">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <label class="form-label">Related Deal</label>
                        <select class="input-control input-sm" id="addEvtDeal" onchange="window.calendarApp.handleDealChange(this.value)">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Assigned Owner</label>
                        <select class="input-control input-sm" id="addEvtOwner">
                            <option value="">-- Select Owner --</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Location / Meeting Link</label>
                    <input type="text" class="input-control input-sm" id="addEvtLocation" placeholder="e.g., Video Meeting (meet.NexFlow.io/demo)">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Description / Notes</label>
                    <textarea class="input-control" id="addEvtDesc" style="height: 60px; font-size: 12px; resize: none;padding-top:6px" placeholder="Event agenda and discussion notes..."></textarea>
                </div>

                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Client Portal Settings</div>
                <div style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #F8FAFC; border: 1px solid var(--border-divider); border-radius: 6px; margin-bottom: 14px;">
                    <input type="checkbox" id="addEvtClientVisible" style="width: 16px; height: 16px; cursor: pointer; accent-color: var(--primary);">
                    <div>
                        <label for="addEvtClientVisible" style="font-size: 12.5px; font-weight: 600; color: var(--text-heading); cursor: pointer; margin: 0;">Visible to Client (Display in Client Portal)</label>
                        <div style="font-size: 11px; color: var(--text-muted);">Enable to display this meeting in the Client Portal for the selected company. Unchecked keeps it internal.</div>
                    </div>
                </div>

                <div style="border-top: 1px dashed var(--border-divider); padding-top: 12px;">
                    <div class="calendar-filter-title">Integrations</div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-secondary btn-xs" disabled style="opacity: 0.6;" title="Integration available in a future backend phase">Sync Google Calendar</button>
                        <button type="button" class="btn btn-secondary btn-xs" disabled style="opacity: 0.6;" title="Integration available in a future backend phase">Sync Outlook</button>
                    </div>
                </div>
            </div>
            <div class="calendar-drawer-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.calendarApp.closeCreateDrawer()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="btnSaveEventSubmit">Save Event</button>
            </div>
        </form>
    </div>
</div>

<!-- Drawer 2: Mobile Filter Drawer (340px wide) -->
<div class="calendar-filter-drawer" id="calendarFilterDrawer" aria-hidden="true">
    <div class="calendar-drawer-header">
        <h3 style="font-size: 15px; font-weight: 600; color: var(--text-heading); margin: 0;">Filter Schedule</h3>
        <button type="button" class="btn btn-ghost btn-xs" onclick="window.calendarApp.closeFilterDrawer()" style="padding: 4px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="calendar-drawer-body">
        <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label">Event Type</label>
            <select class="input-control input-sm" id="mobileFilterTypeSelect">
                <option value="All">All Event Types</option>
                <option value="Call">Call</option>
                <option value="Meeting">Meeting</option>
                <option value="Product Demo">Product Demo</option>
                <option value="Follow-up">Follow-up</option>
                <option value="Proposal">Proposal</option>
                <option value="Task Deadline">Task Deadline</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label">Team Member</label>
            <select class="input-control input-sm" id="mobileFilterOwnerSelect">
                <option value="All">All Team Members</option>
            </select>
        </div>
    </div>
    <div class="calendar-drawer-footer">
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.calendarApp.resetFilters()">Clear All</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.calendarApp.applyMobileFilterDrawer()">Apply Filters</button>
    </div>
</div>

<!-- Drawer 3: Contact Details Drawer (Opened from Calendar Event Details) -->
<div class="calendar-contact-drawer-overlay" id="calendarContactDrawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="ccBreadcrumbName">
    <div class="calendar-contact-drawer-panel" aria-hidden="true">
        <div class="calendar-contact-drawer-header">
            <div>
                <div style="font-size: 12px; color: var(--text-muted);" id="ccBreadcrumbName">Contact Details</div>
                <h3 style="font-size: 18px; font-weight: 600; color: var(--text-heading); margin: 2px 0 0;" id="ccName">Contact Name</h3>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button type="button" class="btn btn-ghost btn-xs" id="btnCloseContactDrawer" onclick="window.calendarApp.closeContactDrawer()" style="padding: 4px;" aria-label="Close drawer">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
        <div class="calendar-contact-drawer-body">
            <!-- Hero Header Profile Card -->
            <div style="display: flex; align-items: flex-start; gap: 16px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border-divider);">
                <div class="avatar avatar-lg" id="ccAvatar" style="width: 56px; height: 56px; border-radius: 50%; font-size: 20px; font-weight: 600; color: white; display: flex; align-items: center; justify-content: center; background-color: var(--primary);">--</div>
                <div style="flex: 1;">
                    <div style="font-size: 14px; color: var(--text-muted); margin-bottom: 4px;" id="ccTitleCompany">Title • Company</div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="status-badge status-customer" id="ccRelBadge">Customer</span>
                    </div>
                </div>
            </div>

            <!-- Contact View Tabs -->
            <div class="contact-view-tabs" role="tablist" style="display: flex; gap: 24px; border-bottom: 1px solid var(--border-divider); margin-bottom: 20px;">
                <button type="button" role="tab" class="tab-btn company-view-tab active" id="tab-cc-overview" onclick="window.calendarApp.switchContactTab('overview')">Overview</button>
                <button type="button" role="tab" class="tab-btn company-view-tab" id="tab-cc-activity" onclick="window.calendarApp.switchContactTab('activity')">Activity</button>
                <button type="button" role="tab" class="tab-btn company-view-tab" id="tab-cc-deals" onclick="window.calendarApp.switchContactTab('deals')">Deals</button>
                <button type="button" role="tab" class="tab-btn company-view-tab" id="tab-cc-tasks" onclick="window.calendarApp.switchContactTab('tasks')">Tasks</button>
                <button type="button" role="tab" class="tab-btn company-view-tab" id="tab-cc-notes" onclick="window.calendarApp.switchContactTab('notes')">Notes</button>
            </div>

            <!-- Tab Content Container -->
            <div id="ccTabContent"></div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

