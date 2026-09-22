<?php
// Global Time Tracker & Timesheets Partial
?>
 
<!-- Time Tracker Anchored Popover -->
<div class="time-tracker-popover" id="timeTrackerPopover">
    <div class="time-tracker-popover-content" id="timeTrackerPopoverContent">
        <!-- Injected via JavaScript (Empty State or Running State) -->
    </div>
</div>

<!-- Timesheets Right-Side Drawer (z-index: 1010) -->
<div class="timesheets-drawer-overlay" id="timesheetsDrawer" onclick="if(event.target===this) window.NexFlowTimer.closeTimesheetsDrawer()">
    <div class="timesheets-drawer-panel" onclick="event.stopPropagation()">
        
        <div class="timesheets-drawer-header">
            <div>
                <h3 class="timesheets-drawer-title">Timesheets</h3>
                <p class="timesheets-drawer-subtitle">Track, review, and manage billable and non-billable time entries.</p>
            </div>
            <div class="timesheets-drawer-actions">
                <button type="button" class="btn btn-primary btn-xs" onclick="window.NexFlowTimer.openManualEntryModal()">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     Manual Entry
                </button>
                <button type="button" class="btn btn-ghost btn-xs" onclick="window.NexFlowTimer.closeTimesheetsDrawer()">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <div class="timesheets-drawer-body">
            
            <!-- Summary Metric Cards -->
            <div class="timesheet-metrics-grid">
                <div class="timesheet-metric-card">
                    <span class="timesheet-metric-label">Today's Time</span>
                    <span class="timesheet-metric-val" id="tsMetricToday">0h 0m</span>
                </div>
                <div class="timesheet-metric-card">
                    <span class="timesheet-metric-label">This Week</span>
                    <span class="timesheet-metric-val" id="tsMetricWeek">0h 0m</span>
                </div>
                <div class="timesheet-metric-card">
                    <span class="timesheet-metric-label">Billable Time</span>
                    <span class="timesheet-metric-val" style="color: #047857;" id="tsMetricBillable">0h 0m</span>
                </div>
                <div class="timesheet-metric-card">
                    <span class="timesheet-metric-label">Non-Billable</span>
                    <span class="timesheet-metric-val" style="color: #64748B;" id="tsMetricNonBillable">0h 0m</span>
                </div>
            </div>

            <!-- Toolbar / Filters -->
            <div class="timesheets-filter-bar">
                <div style="position: relative; flex: 1;">
                    <svg width="14" height="14" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="input-control input-sm" id="tsSearchInput" placeholder="Search entries or records..." style="padding-left: 30px;" oninput="window.NexFlowTimer.handleSearch(this.value)">
                </div>

                <select class="input-control input-sm" style="width: 140px;" id="tsTypeFilter" onchange="window.NexFlowTimer.handleTypeFilter(this.value)">
                    <option value="All">All Types</option>
                    <option value="Task">Tasks</option>
                    <option value="Lead">Leads</option>
                    <option value="Deal">Deals</option>
                    <option value="Company">Companies</option>
                    <option value="General">General</option>
                </select>

                <select class="input-control input-sm" style="width: 130px;" id="tsBillableFilter" onchange="window.NexFlowTimer.handleBillableFilter(this.value)">
                    <option value="All">All Billing</option>
                    <option value="Billable">Billable</option>
                    <option value="Non-Billable">Non-Billable</option>
                </select>
            </div>

            <!-- Timesheets Entries List -->
            <div class="timesheets-entries-container" id="timesheetsEntriesList">
                <!-- Dynamically populated -->
            </div>

        </div>

        <div class="timesheets-drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.NexFlowTimer.exportTimesheetsCSV()">Export CSV</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.NexFlowTimer.closeTimesheetsDrawer()">Close</button>
        </div>

    </div>
</div>

<!-- Top-Level Child Modals Overlay (z-index: 1100, above Drawer) -->
<div class="timer-modal-overlay" id="timerModalOverlay" onclick="if(event.target===this) window.NexFlowTimer.closeModal()">
    <div class="timer-modal-card" id="timerModalCard" onclick="event.stopPropagation()">
        <!-- Injected dynamically -->
    </div>
</div>

<!-- Floating Row Dropdown for Timesheet Actions -->
<div class="timesheet-row-dropdown" id="timesheetRowDropdown"></div>

<!-- Toast Notifications Container -->
<div class="timer-toast-container" id="timerToastContainer"></div>

