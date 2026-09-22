<?php
// Unified Inbox Page

$page_title = "Inbox";
$current_page = "inbox";
$page_script = "inbox.js";

include __DIR__ . '/includes/header.php';
requirePermission('inbox');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';

$currentUser = nexflow_current_user();
?>

<script>
    window.CURRENT_USER_ID = <?php echo (int)($currentUser['id'] ?? 0); ?>;
    window.CURRENT_USER_NAME = <?php echo json_encode($currentUser['name'] ?? 'User'); ?>;
    window.CURRENT_ORG_ID = <?php echo (int)($currentUser['organization_id'] ?? 0); ?>;
</script>

<main class="main-content inbox-page">
    <div class="page-container">
        <!-- Page Header -->
        <div class="inbox-header">
            <div class="inbox-header-left">
                <div class="inbox-title-row">
                    <h1 class="inbox-header-title">Inbox</h1>
                    <span class="inbox-unread-badge" id="inboxTotalUnreadBadge">0 unread</span>
                </div>
                <p class="inbox-header-subtitle">Manage customer conversations and follow-ups.</p>
            </div>
            <div class="inbox-header-right">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.inboxApp.refreshConversations()" title="Refresh conversations">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                    Refresh
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.inboxApp.openComposeDrawer()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Compose
                </button>
            </div>
        </div>

        <!-- Three-Panel Desktop Inbox Layout -->
        <div class="inbox-container">
            <!-- Panel 1: Left Inbox Navigation -->
            <aside class="inbox-nav">
                <button type="button" class="btn btn-primary btn-sm inbox-compose-btn" style="width: 100%; margin-bottom: 12px;" onclick="window.inboxApp.openComposeDrawer()">
                    + Compose
                </button>

                <div class="inbox-section-title">Folders</div>
                <div class="inbox-folder-item active" data-folder="all" onclick="window.inboxApp.setFolder('all', this)">
                    <span>All Conversations</span>
                    <span class="inbox-folder-count" id="count_all">0</span>
                </div>
                <div class="inbox-folder-item" data-folder="unread" onclick="window.inboxApp.setFolder('unread', this)">
                    <span>Unread</span>
                    <span class="inbox-folder-count" id="count_unread">0</span>
                </div>
                <div class="inbox-folder-item" data-folder="assigned" onclick="window.inboxApp.setFolder('assigned', this)">
                    <span>Assigned to Me</span>
                    <span class="inbox-folder-count" id="count_assigned">0</span>
                </div>
                <div class="inbox-folder-item" data-folder="starred" onclick="window.inboxApp.setFolder('starred', this)">
                    <span>Starred</span>
                    <span class="inbox-folder-count" id="count_starred">0</span>
                </div>
                <div class="inbox-folder-item" data-folder="snoozed" onclick="window.inboxApp.setFolder('snoozed', this)">
                    <span>Snoozed</span>
                    <span class="inbox-folder-count" id="count_snoozed">0</span>
                </div>
                <div class="inbox-folder-item" data-folder="archived" onclick="window.inboxApp.setFolder('archived', this)">
                    <span>Archived</span>
                    <span class="inbox-folder-count" id="count_archived">0</span>
                </div>
                <div class="inbox-folder-item" data-folder="drafts" onclick="window.inboxApp.setFolder('drafts', this)">
                    <span>Drafts</span>
                    <span class="inbox-folder-count" id="count_drafts">0</span>
                </div>

                <div class="inbox-section-title">Channels</div>
                <div class="inbox-folder-item" data-channel="Email" onclick="window.inboxApp.setChannelFilter('Email', this)">
                    <span>✉️ Email</span>
                    <span class="inbox-folder-count" id="count_email">0</span>
                </div>
                <div class="inbox-folder-item" data-channel="WhatsApp" onclick="window.inboxApp.setChannelFilter('WhatsApp', this)">
                    <span>💬 WhatsApp</span>
                    <span class="inbox-folder-count" id="count_whatsapp">0</span>
                </div>

                <div class="inbox-section-title">Team Inboxes</div>
                <div class="inbox-folder-item" data-team="Sales Team" onclick="window.inboxApp.setTeamFilter('Sales Team', this)">
                    <span>Sales Team</span>
                    <span class="inbox-folder-count" id="count_sales_team">0</span>
                </div>
                <div class="inbox-folder-item" data-team="Support" onclick="window.inboxApp.setTeamFilter('Support', this)">
                    <span>Support</span>
                    <span class="inbox-folder-count" id="count_support_team">0</span>
                </div>

                <div class="inbox-agent-status">
                    <span class="inbox-status-dot"></span>
                    <div style="min-width: 0; flex: 1;">
                        <div style="font-weight: 600; color: var(--text-heading); font-size: 11.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($currentUser['name'] ?? 'Agent'); ?></div>
                        <div style="font-size: 10px; color: var(--text-muted);"><?php echo htmlspecialchars($currentUser['availability'] ?? 'Available'); ?></div>
                    </div>
                </div>
            </aside>

            <!-- Panel 2: Conversation List -->
            <section class="inbox-list-panel">
                <div class="inbox-list-header">
                    <div style="position: relative;">
                        <input type="text" class="input-control input-sm" id="inboxSearchInput" style="padding-left: 28px;" placeholder="Search conversations...">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="position: absolute; left: 8px; top: 7px; color: var(--text-muted);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </div>
                    <div class="inbox-list-tools">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <input type="checkbox" id="inboxSelectAllCb" title="Select all conversations" onchange="window.inboxApp.toggleSelectAll(this.checked)">
                            <button type="button" class="btn btn-ghost btn-xs" onclick="window.inboxApp.openFilterDrawer()">Filter</button>
                        </div>
                        <select class="input-control input-sm" id="inboxSortSelect" style="font-size: 11px; height: 24px; padding: 0 4px; width: 110px;" onchange="window.inboxApp.setSort(this.value)">
                            <option value="newest">Newest</option>
                            <option value="oldest">Oldest</option>
                            <option value="priority">Priority</option>
                            <option value="unread">Unread First</option>
                        </select>
                    </div>
                </div>

                <div class="inbox-list-body" id="inboxConvList">
                    <!-- Rendered dynamically by JS -->
                </div>
            </section>

            <!-- Panel 3: Active Conversation Thread -->
            <section class="inbox-thread-panel" id="inboxThreadPanel">
                <div class="inbox-thread-header" id="inboxThreadHeader">
                    <!-- Rendered dynamically by JS -->
                </div>

                <div class="inbox-thread-body" id="inboxThreadBody">
                    <!-- Rendered dynamically by JS -->
                </div>

                <div class="inbox-reply-composer" id="inboxReplyComposer">
                    <!-- Rendered dynamically by JS -->
                </div>
            </section>
        </div>
    </div>
</main>

<!-- Compose Modal / Drawer (460px wide) -->
<div class="inbox-compose-drawer" id="inboxComposeDrawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="composeModalTitle">
    <div class="inbox-compose-panel" aria-hidden="true">
        <div style="padding: 14px 18px; border-bottom: 1px solid var(--border-card); display: flex; align-items: center; justify-content: space-between;">
            <h3 style="font-size: 15px; font-weight: 600; color: var(--text-heading); margin: 0;" id="composeModalTitle">New Message</h3>
            <button type="button" class="btn btn-ghost btn-xs" onclick="window.inboxApp.closeComposeDrawer()" style="padding: 4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="composeMessageForm" style="display: flex; flex-direction: column; height: 100%;">
            <input type="hidden" id="composeDraftId" value="">
            <div style="padding: 16px; flex: 1; overflow-y: auto;">
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Channel</label>
                    <select class="input-control input-sm" id="composeChannelSelect" onchange="window.inboxApp.toggleComposeChannelFields(this.value)">
                        <option value="Email" selected>Email</option>
                        <option value="WhatsApp">WhatsApp</option>
                        <option value="Internal Note">Internal Note</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Contact (CRM)</label>
                    <select class="input-control input-sm" id="composeContactSelect" onchange="window.inboxApp.onComposeContactChange(this.value)">
                        <option value="">Select Contact (Optional)...</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Company (CRM)</label>
                    <select class="input-control input-sm" id="composeCompanySelect">
                        <option value="">Select Company (Optional)...</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Deal (CRM)</label>
                    <select class="input-control input-sm" id="composeDealSelect">
                        <option value="">Select Deal (Optional)...</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">To (Contact / Email / Phone) *</label>
                    <input type="text" class="input-control input-sm" id="composeToInput" required placeholder="recipient@example.com or phone...">
                </div>

                <div class="form-group" id="composeSubjectGroup" style="margin-bottom: 12px;">
                    <label class="form-label">Subject</label>
                    <input type="text" class="input-control input-sm" id="composeSubjectInput" placeholder="Subject...">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Message Content *</label>
                    <textarea class="input-control" id="composeBodyInput" required style="height: 120px; font-size: 12.5px; resize: none;" placeholder="Write your message here..."></textarea>
                </div>

                <div style="font-size: 11px; color: var(--text-muted); background-color: #F9FAFB; padding: 8px 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-divider);">
                    💡 Real database persistence enabled. Outbound messages are recorded in CRM communication history.
                </div>
            </div>
            <div style="padding: 12px 16px; border-top: 1px solid var(--border-card); display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.inboxApp.closeComposeDrawer()">Cancel</button>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <button type="button" class="btn btn-secondary btn-sm" id="composeSaveDraftBtn" onclick="window.inboxApp.saveDraft()">Save Draft</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="composeSubmitBtn">Send & Open Email</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Mobile Filter Drawer (340px wide) -->
<div class="inbox-filter-drawer" id="inboxFilterDrawer" aria-hidden="true">
    <div style="padding: 14px 18px; border-bottom: 1px solid var(--border-card); display: flex; align-items: center; justify-content: space-between;">
        <h3 style="font-size: 15px; font-weight: 600; color: var(--text-heading); margin: 0;">Filter Inbox</h3>
        <button type="button" class="btn btn-ghost btn-xs" onclick="window.inboxApp.closeFilterDrawer()" style="padding: 4px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div style="padding: 16px; flex: 1; overflow-y: auto;">
        <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label">Channel</label>
            <select class="input-control input-sm" id="filterChannelSelect">
                <option value="All">All Channels</option>
                <option value="Email">Email</option>
                <option value="WhatsApp">WhatsApp</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label">Read Status</label>
            <select class="input-control input-sm" id="filterReadSelect">
                <option value="All">All Statuses</option>
                <option value="Unread">Unread Only</option>
                <option value="Read">Read Only</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 14px;">
            <label class="form-label">Priority</label>
            <select class="input-control input-sm" id="filterPrioritySelect">
                <option value="All">All Priorities</option>
                <option value="Urgent">Urgent</option>
                <option value="High">High</option>
                <option value="Normal">Normal</option>
            </select>
        </div>
    </div>
    <div style="padding: 12px 16px; border-top: 1px solid var(--border-card); display: flex; align-items: center; justify-content: space-between;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.inboxApp.resetFilters()">Clear All</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.inboxApp.applyMobileFilters()">Apply Filters</button>
    </div>
</div>

<!-- Contact Context Drawer (opens when avatar / name / company is clicked) -->
<div class="inbox-contact-drawer" id="inboxContactDrawer"
     role="dialog" aria-modal="true" aria-hidden="true"
     aria-labelledby="inboxContactDrawerName">
    <div class="inbox-contact-panel" id="inboxContactPanel">
        <!-- Panel Header -->
        <div class="inbox-contact-panel-header">
            <div style="min-width:0;">
                <div style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 2px;">Contact Details</div>
                <div id="inboxContactDrawerName" style="font-size: 15px; font-weight: 700; color: var(--text-heading); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></div>
            </div>
            <button type="button"
                    id="inboxContactDrawerClose"
                    class="btn btn-ghost btn-xs"
                    onclick="window.inboxApp.closeContactDrawer()"
                    aria-label="Close contact details"
                    style="padding: 5px; flex-shrink: 0;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <!-- Panel Body (rendered by JS) -->
        <div class="inbox-contact-panel-body" id="inboxContactDrawerBody"></div>
    </div>
</div>

<!-- Company Detail Drawer (opens when View Company is clicked) -->
<div class="inbox-contact-drawer" id="inboxCompanyDrawer"
     role="dialog" aria-modal="true" aria-hidden="true"
     aria-labelledby="inboxCompanyDrawerName">
    <div class="inbox-contact-panel" id="inboxCompanyPanel">
        <!-- Panel Header -->
        <div class="inbox-contact-panel-header">
            <div style="min-width:0;">
                <div style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 2px;">Company Details</div>
                <div id="inboxCompanyDrawerName" style="font-size: 15px; font-weight: 700; color: var(--text-heading); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></div>
            </div>
            <button type="button"
                    id="inboxCompanyDrawerClose"
                    class="btn btn-ghost btn-xs"
                    onclick="window.inboxApp.closeCompanyDrawer()"
                    aria-label="Close company details"
                    style="padding: 5px; flex-shrink: 0;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <!-- Panel Body (rendered by JS) -->
        <div class="inbox-contact-panel-body" id="inboxCompanyDrawerBody"></div>
    </div>
</div>

<!-- Deal Detail Drawer (opens when View Deal is clicked) -->
<div class="inbox-contact-drawer" id="inboxDealDrawer"
     role="dialog" aria-modal="true" aria-hidden="true"
     aria-labelledby="inboxDealDrawerName">
    <div class="inbox-contact-panel" id="inboxDealPanel">
        <!-- Panel Header -->
        <div class="inbox-contact-panel-header">
            <div style="min-width:0;">
                <div style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 2px;">Deal Details</div>
                <div id="inboxDealDrawerName" style="font-size: 15px; font-weight: 700; color: var(--text-heading); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></div>
            </div>
            <button type="button"
                    id="inboxDealDrawerClose"
                    class="btn btn-ghost btn-xs"
                    onclick="window.inboxApp.closeDealDrawer()"
                    aria-label="Close deal details"
                    style="padding: 5px; flex-shrink: 0;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <!-- Panel Body (rendered by JS) -->
        <div class="inbox-contact-panel-body" id="inboxDealDrawerBody"></div>
    </div>
</div>

<!-- Snooze Conversation Modal -->
<div class="inbox-compose-drawer" id="inboxSnoozeModal" role="dialog" aria-modal="true" aria-hidden="true" style="display:none; align-items:center; justify-content:center; background-color: rgba(16, 24, 40, 0.45); backdrop-filter: blur(2px);">
    <div style="background:var(--bg-card, #fff); border-radius:var(--radius-md, 8px); box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); width:100%; max-width:380px; margin:auto; border:1px solid var(--border-card, #E5E7EB); overflow:hidden;">
        <div style="padding:14px 18px; border-bottom:1px solid var(--border-card, #E5E7EB); display:flex; align-items:center; justify-content:space-between;">
            <h3 style="font-size:15px; font-weight:600; color:var(--text-heading); margin:0;">⏰ Snooze Conversation</h3>
            <button type="button" class="btn btn-ghost btn-xs" onclick="window.inboxApp.closeSnoozeModal()" style="padding:4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div style="padding:16px;">
            <input type="hidden" id="snoozeConvId" value="">
            <div style="font-size:12px; color:var(--text-muted); margin-bottom:12px;">Choose when this conversation should reappear in your active Inbox:</div>
            
            <div style="display:flex; flex-direction:column; gap:8px; margin-bottom:14px;">
                <button type="button" class="btn btn-secondary btn-sm" style="text-align:left; justify-content:flex-start;" onclick="window.inboxApp.applyPresetSnooze('later_today')">
                    ☀️ Later Today (6:00 PM)
                </button>
                <button type="button" class="btn btn-secondary btn-sm" style="text-align:left; justify-content:flex-start;" onclick="window.inboxApp.applyPresetSnooze('tomorrow')">
                    🌅 Tomorrow Morning (9:00 AM)
                </button>
                <button type="button" class="btn btn-secondary btn-sm" style="text-align:left; justify-content:flex-start;" onclick="window.inboxApp.applyPresetSnooze('next_week')">
                    📅 Next Week (Monday 9:00 AM)
                </button>
            </div>

            <div style="border-top:1px solid var(--border-divider, #F3F4F6); padding-top:12px;">
                <label class="form-label" style="font-size:12px; margin-bottom:6px; display:block;">Custom Date & Time</label>
                <div style="display:flex; gap:6px;">
                    <input type="datetime-local" class="input-control input-sm" id="snoozeCustomInput" style="flex:1;">
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.inboxApp.applyCustomSnooze()">Snooze</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit CRM Relationship Modal -->
<div class="inbox-compose-drawer" id="inboxEditRelationshipModal" role="dialog" aria-modal="true" aria-hidden="true" style="display:none; align-items:center; justify-content:center; background-color: rgba(16, 24, 40, 0.45); backdrop-filter: blur(2px);">
    <div style="background:var(--bg-card, #fff); border-radius:var(--radius-md, 8px); box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); width:100%; max-width:420px; margin:auto; border:1px solid var(--border-card, #E5E7EB); overflow:hidden;">
        <div style="padding:14px 18px; border-bottom:1px solid var(--border-card, #E5E7EB); display:flex; align-items:center; justify-content:space-between;">
            <h3 style="font-size:15px; font-weight:600; color:var(--text-heading); margin:0;">🔗 Link CRM Records</h3>
            <button type="button" class="btn btn-ghost btn-xs" onclick="window.inboxApp.closeEditRelationshipModal()" style="padding:4px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div style="padding:16px;">
            <input type="hidden" id="editRelConvId" value="">
            
            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Contact</label>
                <select class="input-control input-sm" id="editRelContactSelect">
                    <option value="">-- None --</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label class="form-label">Company</label>
                <select class="input-control input-sm" id="editRelCompanySelect">
                    <option value="">-- None --</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label class="form-label">Deal</label>
                <select class="input-control input-sm" id="editRelDealSelect">
                    <option value="">-- None --</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.inboxApp.closeEditRelationshipModal()">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.inboxApp.saveRelationship()">Save Links</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

