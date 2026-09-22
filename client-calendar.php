<?php
require_once __DIR__ . '/includes/client-auth.php';
require_once __DIR__ . '/includes/client-helpers.php';
require_once __DIR__ . '/includes/client-calendar-data.php';

$client = require_client_auth();
$orgId = (int)$client['organization_id'];
$companyId = (int)$client['company_id'];

$page_title = "Calendar & Schedule — NexFlow Client Portal";
$active_nav = "calendar";
$page_heading = "Meetings & Schedule";

// Preload live calendar data strictly isolated to client company
$calData = client_get_calendar_data(nexflow_db(), $orgId, $companyId);
$initialMeetings = $calData['meetings'];
$initialKpis = $calData['kpis'];

include __DIR__ . '/includes/client-header.php';
include __DIR__ . '/includes/client-sidebar.php';
?>

<div class="client-portal-main-wrapper">
    <?php include __DIR__ . '/includes/client-topbar.php'; ?>

    <main class="client-portal-content">
        
        <div style="margin-bottom:20px;">
            <p style="font-size:13.5px;color:#64748B;margin:4px 0 0 0;">View your scheduled review sessions, executive briefings, and request new appointments with the NexFlow team.</p>
        </div>

        <!-- Toolbar & View Switcher -->
        <div class="client-portal-toolbar">
            <div class="client-portal-toolbar-left">
                <div style="display:flex;gap:4px;background:#E2E8F0;padding:3px;border-radius:6px;">
                    <button type="button" class="btn btn-ghost btn-xs active" id="btnCalMonth" onclick="setCalendarView('month', this)" style="background:#FFFFFF;font-weight:600;">Month</button>
                    <button type="button" class="btn btn-ghost btn-xs" id="btnCalWeek" onclick="setCalendarView('week', this)">Week</button>
                    <button type="button" class="btn btn-ghost btn-xs" id="btnCalAgenda" onclick="setCalendarView('agenda', this)">Agenda List</button>
                </div>
                <div style="display:flex;align-items:center;gap:6px;margin-left:12px;">
                    <button type="button" class="btn btn-ghost btn-xs" id="btnCalPrev" onclick="navigateCalMonth(-1)" title="Previous Month" style="padding:2px 6px;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <div style="font-size:15px;font-weight:700;color:#0F172A;" id="calPeriodLabel"><?php echo date('F Y'); ?></div>
                    <button type="button" class="btn btn-ghost btn-xs" id="btnCalNext" onclick="navigateCalMonth(1)" title="Next Month" style="padding:2px 6px;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>

            <div class="client-portal-toolbar-right">
                <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.openQuickMessageModal('Meeting Request')">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
                    Request New Meeting
                </button>
            </div>
        </div>

        <!-- Calendar Container -->
        <div class="client-portal-card" id="calendarMainCard">
            <div class="client-portal-card-body" id="calendarViewContainer" style="padding:16px;">
                <!-- Populated by JavaScript -->
            </div>
        </div>

        <!-- Sync Integration Notice -->
        <div style="margin-top:16px;padding:12px 16px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;display:flex;align-items:center;gap:10px;font-size:12.5px;color:#64748B;">
            <svg width="16" height="16" fill="none" stroke="#2563EB" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span>Your live meeting calendar with the NexFlow team. All times displayed in your account timezone.</span>
        </div>

    </main>
</div>

<script>
let currentCalView = "month";
let calYear = <?php echo (int)date('Y'); ?>;
let calMonth = <?php echo (int)date('n'); ?>; // 1-12
let clientMeetingsList = <?php echo json_encode($initialMeetings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || [];

const monthNames = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
];

function updatePeriodLabel() {
    const lbl = document.getElementById("calPeriodLabel");
    if (lbl) {
        lbl.textContent = `${monthNames[calMonth - 1]} ${calYear}`;
    }
}

function navigateCalMonth(offset) {
    calMonth += offset;
    if (calMonth < 1) {
        calMonth = 12;
        calYear--;
    } else if (calMonth > 12) {
        calMonth = 1;
        calYear++;
    }
    updatePeriodLabel();
    fetchClientCalendar();
}

async function fetchClientCalendar() {
    const monthParam = `${calYear}-${String(calMonth).padStart(2, '0')}`;
    try {
        const res = await fetch(`api/client-calendar.php?action=list&month=${encodeURIComponent(monthParam)}`);
        const json = await res.json();
        if (json.success && Array.isArray(json.data.meetings)) {
            clientMeetingsList = json.data.meetings;
        }
    } catch (e) {
        console.error("Failed to fetch calendar events:", e);
    }
    renderClientCalendar();
}

function setCalendarView(viewName, btn) {
    currentCalView = viewName;
    document.querySelectorAll(".client-portal-toolbar-left .btn-ghost").forEach(b => {
        b.classList.remove("active");
        b.style.background = "none";
        b.style.fontWeight = "500";
    });
    if (btn) {
        btn.classList.add("active");
        btn.style.background = "#FFFFFF";
        btn.style.fontWeight = "600";
    }
    renderClientCalendar();
}

function renderClientCalendar() {
    const container = document.getElementById("calendarViewContainer");
    if (!container) return;

    updatePeriodLabel();

    if (currentCalView === "agenda" || currentCalView === "week") {
        if (clientMeetingsList.length === 0) {
            container.innerHTML = `
                <div style="padding:40px;text-align:center;color:#64748B;">
                    <svg width="36" height="36" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 8px auto;display:block;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    No meetings scheduled for this period.
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <div style="display:flex;flex-direction:column;gap:12px;">
                ${clientMeetingsList.map(m => {
                    const parts = m.date.split('-');
                    const monthShort = monthNames[parseInt(parts[1], 10) - 1].substring(0, 3).toUpperCase();
                    const dayNum = parseInt(parts[2], 10);
                    const statusColor = (m.status === 'Completed') ? '#059669' : (m.status === 'Cancelled' ? '#DC2626' : '#2563EB');
                    return `
                    <div style="padding:14px 18px;border:1px solid #E2E8F0;border-radius:8px;background:#F8FAFC;display:flex;align-items:center;justify-content:space-between;gap:16px;">
                        <div style="display:flex;align-items:center;gap:14px;">
                            <div style="width:48px;height:48px;border-radius:8px;background:#EFF6FF;color:#2563EB;display:flex;flex-direction:column;align-items:center;justify-content:center;font-weight:700;line-height:1.1;flex-shrink:0;">
                                <span style="font-size:11px;text-transform:uppercase;">${monthShort}</span>
                                <span style="font-size:16px;">${dayNum}</span>
                            </div>
                            <div>
                                <div style="font-size:14px;font-weight:700;color:#0F172A;">${m.title}</div>
                                <div style="font-size:12px;color:#64748B;margin-top:2px;">Time: ${m.time} • Host: ${m.host}</div>
                                <div style="font-size:11.5px;color:${statusColor};font-weight:600;margin-top:2px;">${m.location} (${m.status})</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-xs" onclick="openEventDetailsModal('${m.id}')">View Details</button>
                    </div>
                    `;
                }).join("")}
            </div>
        `;
    } else {
        // Month Grid View
        const days = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        let gridHtml = `
            <div style="display:grid;grid-template-columns:repeat(7, 1fr);gap:6px;margin-bottom:8px;text-align:center;">
                ${days.map(d => `<div style="font-size:12px;font-weight:600;color:#64748B;padding:6px 0;">${d}</div>`).join("")}
            </div>
            <div style="display:grid;grid-template-columns:repeat(7, 1fr);gap:6px;">
        `;

        // Determine first day weekday and total days in month
        const firstDayWeekday = new Date(calYear, calMonth - 1, 1).getDay();
        const totalDays = new Date(calYear, calMonth, 0).getDate();

        // Empty padding cells for preceding days of week
        for (let p = 0; p < firstDayWeekday; p++) {
            gridHtml += `<div style="min-height:85px;border:1px dashed #E2E8F0;border-radius:6px;padding:6px;background:#F8FAFC;opacity:0.4;"></div>`;
        }

        const todayStr = "<?php echo date('Y-m-d'); ?>";

        for (let i = 1; i <= totalDays; i++) {
            const dateStr = `${calYear}-${String(calMonth).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            const meets = clientMeetingsList.filter(m => m.date === dateStr);
            const isToday = (dateStr === todayStr);

            gridHtml += `
                <div style="min-height:85px;border:1px solid ${isToday ? '#2563EB' : '#E2E8F0'};border-radius:6px;padding:6px;background:${meets.length > 0 ? '#EFF6FF' : '#FFFFFF'};">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-size:11px;font-weight:700;color:${isToday ? '#2563EB' : (meets.length > 0 ? '#2563EB' : '#64748B')};">${i}</span>
                        ${isToday ? '<span style="font-size:9.5px;font-weight:700;color:#2563EB;background:#DBEAFE;padding:1px 4px;border-radius:3px;">Today</span>' : ''}
                    </div>
                    ${meets.map(m => {
                        const bgChip = (m.status === 'Completed') ? '#059669' : (m.status === 'Cancelled' ? '#94A3B8' : '#2563EB');
                        return `
                        <div style="font-size:10.5px;background:${bgChip};color:#FFFFFF;padding:2px 4px;border-radius:4px;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer;" onclick="openEventDetailsModal('${m.id}')" title="${m.title} (${m.time})">
                            ${m.title}
                        </div>
                        `;
                    }).join("")}
                </div>
            `;
        }

        gridHtml += `</div>`;
        container.innerHTML = gridHtml;
    }
}

function openEventDetailsModal(eventId) {
    const ev = clientMeetingsList.find(m => m.id === eventId || String(m.raw_id) === String(eventId));
    if (!ev) return;

    const statusColor = (ev.status === 'Completed') ? 'green' : (ev.status === 'Cancelled' ? 'gray' : 'blue');

    const html = `
        <div class="client-portal-modal-header">
            <h3 class="client-portal-modal-title">Meeting: ${ev.title}</h3>
            <button type="button" class="client-portal-drawer-close" onclick="window.clientPortal.closeModal()">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="client-portal-modal-body">
            <div style="margin-bottom:14px;">
                <div style="font-size:11px;color:#64748B;font-weight:600;">DATE &amp; TIME</div>
                <div style="font-size:14px;font-weight:700;color:#0F172A;margin-top:2px;">${ev.date} • ${ev.time}</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                    <div style="font-size:11px;color:#64748B;font-weight:600;">HOST</div>
                    <div style="font-size:13px;color:#334155;margin-top:2px;">${ev.host}</div>
                </div>
                <div>
                    <div style="font-size:11px;color:#64748B;font-weight:600;">STATUS</div>
                    <div style="margin-top:2px;">
                        <span class="client-portal-badge ${statusColor}">${ev.status}</span>
                    </div>
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <div style="font-size:11px;color:#64748B;font-weight:600;">VIDEO ROOM / LOCATION</div>
                <div style="font-size:13px;color:#2563EB;margin-top:2px;font-weight:500;">${ev.location}</div>
            </div>
            ${ev.description ? `
            <div style="margin-bottom:10px;">
                <div style="font-size:11px;color:#64748B;font-weight:600;margin-bottom:4px;">DESCRIPTION / AGENDA</div>
                <p style="font-size:13px;color:#475569;line-height:1.5;margin:0;background:#F8FAFC;padding:10px;border-radius:6px;border:1px solid #E2E8F0;">
                    ${ev.description}
                </p>
            </div>
            ` : ''}
        </div>
        <div class="client-portal-modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientPortal.closeModal()">Close</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.clientPortal.closeModal();if(window.clientPortal && window.clientPortal.openQuickMessageModal)window.clientPortal.openQuickMessageModal('Reschedule Meeting: ' + '${ev.title}');">
                Request Reschedule
            </button>
        </div>
    `;
    window.clientPortal.openModal(html);
}

document.addEventListener("DOMContentLoaded", () => renderClientCalendar());
</script>

<?php include __DIR__ . '/includes/client-footer.php'; ?>