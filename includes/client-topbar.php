<?php
// includes/client-topbar.php
require_once __DIR__ . '/client-auth.php';
if (!isset($client)) {
    $client = client_current_user();
}

if (!isset($page_heading)) {
    $page_heading = "Client Workspace";
}
?>
<!-- Client Portal Top Header Bar -->
<header class="client-portal-topbar">
    <div class="client-portal-topbar-left">
        <!-- Mobile Sidebar Hamburger Toggle -->
        <button type="button" class="client-portal-mobile-toggle" id="clientMobileToggle" aria-label="Toggle navigation menu" onclick="window.clientPortal.toggleMobileSidebar()">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <h1 class="client-portal-page-title"><?php echo htmlspecialchars($page_heading); ?></h1>
    </div>

    <!-- Right Controls: Global Search, Quick Contact Manager, Notifications, User Menu -->
    <div class="client-portal-topbar-right">
        
        <!-- Portal Quick Search -->
        <div class="client-portal-search-wrapper">
            <svg class="client-portal-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" class="client-portal-search-input" id="clientGlobalSearchInput" placeholder="Search deals, tasks, files..." oninput="window.clientPortal.handleGlobalSearch(this.value)">
            <!-- Search Results Dropdown -->
            <div class="client-portal-search-results" id="clientSearchResults"></div>
        </div>
 
        <!-- Account Manager Quick Contact Pill -->
        <button type="button" class="client-portal-manager-pill" onclick="window.clientPortal.openQuickMessageModal()" title="Contact Account Manager">
            <div class="client-portal-avatar avatar-xs" style="background-color:#7C3AED;font-size:10px;width:22px;height:22px;"><?php echo htmlspecialchars($client['ae_avatar_initials'] ?? 'AE'); ?></div>
            <span class="client-portal-manager-text"><?php echo htmlspecialchars(!empty($client['ae_name']) ? ($client['ae_name'] . ' (' . ($client['ae_title'] ?? 'Account Exec') . ')') : 'Account Executive'); ?></span>
            <span class="client-portal-online-dot"></span>
        </button>

        <!-- Notifications Bell & Menu -->
        <div class="client-portal-dropdown-wrapper">
            <button type="button" class="client-portal-icon-btn" id="clientNotifBtn" aria-label="Notifications" aria-expanded="false" onclick="window.clientPortal.toggleDropdown('clientNotifMenu')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span class="client-portal-badge-dot" id="clientNotifBadge"></span>
            </button>
            <div class="client-portal-menu client-portal-notif-menu" id="clientNotifMenu">
                <div class="client-portal-menu-header">
                    <span style="font-weight:600;font-size:13px;color:var(--text-heading);">Portal Notifications</span>
                    <button type="button" class="client-portal-link-btn" onclick="window.clientPortal.markAllNotifsRead()">Mark read</button>
                </div>
                <div class="client-portal-notif-list" id="clientNotifList">
                    <!-- Populated dynamically by client-portal.js -->
                </div>
                <div class="client-portal-menu-footer">
                    <a href="client-messages.php" class="client-portal-menu-footer-link">Open Messages Inbox</a>
                </div>
            </div>
        </div>

        <!-- Client User Dropdown -->
        <div class="client-portal-dropdown-wrapper">
            <button type="button" class="client-portal-user-trigger" id="clientUserMenuBtn" aria-label="User Account Menu" aria-expanded="false" onclick="window.clientPortal.toggleDropdown('clientUserMenu')">
                <div class="client-portal-avatar" id="topbarUserAvatar" style="background-color:#2563EB;"><?php echo htmlspecialchars($client['avatar_initials'] ?? 'CP'); ?></div>
                <div class="client-portal-user-text">
                    <span class="client-portal-user-title" id="topbarUserName"><?php echo htmlspecialchars($client['contact_name'] ?? 'Client User'); ?></span>
                    <span class="client-portal-user-sub" id="topbarCompanyName"><?php echo htmlspecialchars($client['company_name'] ?? 'Client Workspace'); ?></span>
                </div>
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted);">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
            <div class="client-portal-menu client-portal-user-dropdown" id="clientUserMenu">
                <div class="client-portal-user-dropdown-header">
                    <div style="font-weight:600;font-size:13px;color:var(--text-heading);" id="menuUserName"><?php echo htmlspecialchars($client['contact_name'] ?? 'Client User'); ?></div>
                    <div style="font-size:11.5px;color:var(--text-secondary);" id="menuUserEmail"><?php echo htmlspecialchars($client['contact_email'] ?? ($client['portal_email'] ?? '')); ?></div>
                    <div class="client-portal-account-tag"><?php echo htmlspecialchars($client['company_name'] ?? 'Client Workspace'); ?></div>
                </div>
                <div class="client-portal-menu-divider"></div>
                <a href="client-profile.php" class="client-portal-menu-item">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    My Profile
                </a>
                <a href="client-help.php" class="client-portal-menu-item">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Help &amp; Support
                </a>
                <div class="client-portal-menu-divider"></div>
                <button type="button" class="client-portal-menu-item danger" onclick="window.clientPortal.logout()">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Sign out
                </button>
            </div>
        </div>

    </div>
</header>
