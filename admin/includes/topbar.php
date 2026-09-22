<?php
// Shared Topbar Partial
if (!isset($page_title)) {
    $page_title = "Dashboard";
}
?>

<header class="topbar">
    <div class="topbar-left">
        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Navigation">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
        <h1 class="topbar-title"><?php echo htmlspecialchars($page_title); ?></h1>
    </div>

    <div class="topbar-search">
        <div class="input-field-container" style="position: relative;">
            <span class="input-icon">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
            </span>
            <input type="text" class="input-control input-sm has-icon" id="globalSearchInput" placeholder="Search leads, contacts, companies…" autocomplete="off" style="padding-right: 28px;">
            <button type="button" id="globalSearchClearBtn" style="display:none; position:absolute; right:8px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; padding:2px; align-items:center; justify-content:center;" aria-label="Clear search">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="global-search-popover" id="globalSearchResultsPopover"></div>
    </div>

    <div class="topbar-right">
        <!-- Quick Add Dropdown -->
        <div style="position: relative;">
            <button class="btn btn-primary btn-sm" id="quickAddBtn">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>Quick Add</span>
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="opacity: 0.7;">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
            <div class="dropdown-menu" id="quickAddMenu" style="right: 0; top: 38px; width: 180px;">
                <button class="dropdown-item" onclick="window.quickAddApp.open('lead')">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="17" y1="11" x2="23" y2="11"/></svg>
                    New Lead
                </button>
                <button class="dropdown-item" onclick="window.quickAddApp.open('contact')">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    New Contact
                </button>
                <button class="dropdown-item" onclick="window.quickAddApp.open('company')">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/></svg>
                    New Company
                </button>
                <button class="dropdown-item" onclick="window.quickAddApp.open('deal')">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                    New Deal
                </button>
                <button class="dropdown-item" onclick="window.quickAddApp.open('task')">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                    New Task
                </button>
            </div>
        </div>

        <!-- Softphone Entry Point Button -->
        <button class="btn btn-ghost btn-sm topbar-phone-btn" id="topbarPhoneBtn" title="Softphone Dialer" aria-label="Open Softphone">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
            </svg>
        </button>

        <!-- Sticky Notes Global Entry Point Button -->
        <button class="btn btn-ghost btn-sm topbar-notes-btn" id="topbarNotesBtn" title="Sticky Notes" aria-label="Open Sticky Notes" aria-expanded="false">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15.5 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V8.5L15.5 3z"/>
                <path d="M14 3v6h6"/>
                <line x1="8" y1="13" x2="16" y2="13"/>
                <line x1="8" y1="17" x2="13" y2="17"/>
            </svg>
            <span class="sticky-notes-topbar-badge" id="stickyNotesBadge" style="display:none;">0</span>
        </button>

        <!-- Global Time Tracker Button & Popover Anchor -->
        <div style="position: relative;" id="topbarTimerContainer">
            <button class="btn btn-ghost btn-sm topbar-timer-btn" id="topbarTimerBtn" title="Time Tracker" aria-label="Time Tracker" aria-expanded="false" onclick="window.NexFlowTimer && window.NexFlowTimer.togglePopover(event)">
                <span class="timer-btn-content" id="topbarTimerContent">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </span>
                <span class="topbar-timer-active-badge" id="topbarTimerActiveBadge" style="display:none;">
                    <span class="timer-pulse-dot"></span>
                    <span class="timer-active-digits" id="topbarTimerDigits">00:00:00</span>
                </span>
            </button>
        </div>

        <!-- Notifications Popover -->
        <div style="position: relative;"> 
            <button class="btn btn-ghost btn-sm" id="notifBtn" style="position: relative; padding: 6px 8px;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
                <span id="notifBadge" style="position: absolute; top: 4px; right: 4px; width: 8px; height: 8px; border-radius: 50%; background-color: #F04438; border: 2px solid #FFFFFF;"></span>
            </button>
            <div class="dropdown-menu" id="notifMenu" style="right: 0; top: 42px; width: 320px; padding: 0;">
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border-card);">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-weight: 600; font-size: 14px; color: var(--text-heading);">Notifications</span>
                        <span id="notifCountTag" style="background-color: var(--primary-light); color: var(--primary); font-size: 11px; font-weight: 600; padding: 1px 6px; border-radius: 999px;">3</span>
                    </div>
                    <button style="background: none; border: none; font-size: 12px; color: var(--primary); font-weight: 500; cursor: pointer;" onclick="markAllNotificationsRead()">Mark all read</button>
                </div>
                <div id="notifListContainer" style="max-height: 280px; overflow-y: auto;">
                    <div class="notif-item" style="padding: 12px 16px; border-bottom: 1px solid var(--border-divider); display: flex; gap: 12px; background-color: #F5F8FF;">
                        <div style="width: 28px; height: 28px; border-radius: 50%; background-color: var(--primary-light); display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">
                            <svg width="14" height="14" fill="none" stroke="#2563EB" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                        </div>
                        <div style="flex: 1;">
                            <p style="font-size: 13px; color: var(--text-heading); font-weight: 500; line-height: 1.3;">New lead: Victoria Chen from Synapse AI</p>
                            <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px;">1h ago</span>
                        </div>
                    </div>
                    <div class="notif-item" style="padding: 12px 16px; border-bottom: 1px solid var(--border-divider); display: flex; gap: 12px; background-color: #F5F8FF;">
                        <div style="width: 28px; height: 28px; border-radius: 50%; background-color: var(--primary-light); display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">
                            <svg width="14" height="14" fill="none" stroke="#2563EB" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
                        </div>
                        <div style="flex: 1;">
                            <p style="font-size: 13px; color: var(--text-heading); font-weight: 500; line-height: 1.3;">Task due: Demo call with BrightPath Analytics</p>
                            <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px;">2h ago</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Help Link -->
        <a href="help.php" class="btn btn-ghost btn-sm" style="padding: 6px 8px;" title="Help & Support">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </a>

        <!-- User Profile Dropdown -->
        <div style="position: relative;">
            <?php
            if (!isset($user) || !is_array($user)) {
                require_once __DIR__ . '/auth.php';
                $user = nexflow_current_user() ?? [];
            }

            $displayUserName = !empty($user['name']) ? $user['name'] : 'Olivia Carter';
            $displayUserEmail = !empty($user['email']) ? $user['email'] : '';
            $displayUserRole = !empty($user['role_label']) ? $user['role_label'] : nexflow_role_label($user['role_id'] ?? $user['role'] ?? 'super_admin');
            $nameParts = explode(' ', trim($displayUserName));
            $userInitials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
            $userPhotoUrl = (!empty($user['photo_path']) && file_exists(__DIR__ . '/../../' . ltrim($user['photo_path'], '/'))) ? ('/nexFlow/' . ltrim($user['photo_path'], '/')) : null;
            ?>
            <button class="btn btn-ghost btn-sm" id="userMenuBtn" style="padding: 4px; gap: 6px;">
                <?php if ($userPhotoUrl): ?>
                    <img src="<?php echo htmlspecialchars($userPhotoUrl); ?>" alt="<?php echo htmlspecialchars($displayUserName); ?>" class="avatar avatar-sm topbar-user-photo" style="object-fit: cover; border-radius: 50%; width: 32px; height: 32px;">
                <?php else: ?>
                    <div class="avatar avatar-sm topbar-user-avatar" style="background-color: #7C3AED;"><?php echo htmlspecialchars($userInitials); ?></div>
                <?php endif; ?>
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color: var(--text-muted);">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
            <div class="dropdown-menu" id="userMenu" style="right: 0; top: 44px; width: 190px;">
                <div style="padding: 10px 14px; border-bottom: 1px solid var(--border-divider); margin-bottom: 4px;">
                    <p style="font-weight: 600; font-size: 13px; color: var(--text-heading);"><?php echo htmlspecialchars($displayUserName); ?></p>
                    <p style="font-size: 11px; color: var(--text-secondary);"><?php echo htmlspecialchars($displayUserEmail ?: $displayUserRole); ?></p>
                </div>
                <a href="settings.php#my-profile" class="dropdown-item">My Profile</a>
                <a href="settings.php#preferences" class="dropdown-item">Account Settings</a>
                <div class="dropdown-divider"></div>
                <button class="dropdown-item danger" onclick="showConfirmModal({ title: 'Sign Out', message: 'Are you sure you want to sign out of your NexFlow CRM workspace?', confirmText: 'Sign Out', type: 'danger', onConfirm: function() { window.location.href = '/nexFlow/admin/logout.php'; } })">Sign out</button>
            </div>
        </div>
    </div>
</header>

