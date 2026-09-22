<?php
// includes/client-sidebar.php
require_once __DIR__ . '/client-auth.php';
require_once __DIR__ . '/client-helpers.php';

if (!isset($client)) {
    $client = client_current_user();
}

$shellCounts = [
    'projects'  => 0,
    'deals'     => 0,
    'proposals' => 0,
    'estimates' => 0,
    'contracts' => 0,
    'invoices'  => 0,
    'tasks'     => 0,
    'messages'  => 0,
];

if ($client) {
    $shellCounts = client_get_shell_counts(
        nexflow_db(),
        (int)$client['organization_id'],
        (int)$client['company_id'],
        (int)$client['contact_id']
    );
}
?>
<!-- Mobile Sidebar Backdrop Overlay -->
<div class="client-portal-sidebar-backdrop" id="clientSidebarBackdrop" onclick="window.clientPortal.toggleMobileSidebar()"></div>

<!-- Dark Navy Client Portal Sidebar -->
<aside class="client-portal-sidebar" id="clientSidebar">
    <!-- Brand Logo Section -->
    <div class="client-portal-sidebar-header">
        <a href="client-dashboard.php" class="client-portal-brand">
            <div class="client-portal-brand-icon">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
            </div>
            <div class="client-portal-brand-text">
                <span class="client-portal-brand-title">NexFlow</span>
                <span class="client-portal-brand-badge">Client Portal</span>
            </div>
        </a>
        <button type="button" class="client-portal-sidebar-close-btn" id="clientSidebarCloseBtn" aria-label="Close Sidebar" onclick="window.clientPortal.toggleMobileSidebar()">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>

    <!-- Client Workspace Scope Indicator -->
    <div class="client-portal-workspace-card">
        <div class="client-portal-workspace-icon">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        </div>
        <div class="client-portal-workspace-info">
            <div class="client-portal-workspace-name" id="sidebarCompanyName"><?php echo htmlspecialchars($client['company_name'] ?? 'Client Workspace'); ?></div>
            <div class="client-portal-workspace-sub"><?php echo htmlspecialchars(!empty($client['company_industry']) ? ($client['company_industry'] . ' Workspace') : 'Client Workspace'); ?></div>
        </div>
    </div>

    <!-- Sidebar Navigation Links (8 Items) -->
    <nav class="client-portal-nav" aria-label="Client Navigation">
        <div class="client-portal-nav-group-label">WORKSPACE MENU</div>
        
        <!-- 1. Dashboard -->
        <a href="client-dashboard.php" class="client-portal-nav-item <?php echo ($active_nav === 'dashboard') ? 'active' : ''; ?>" aria-current="<?php echo ($active_nav === 'dashboard') ? 'page' : 'false'; ?>">
            <svg class="client-portal-nav-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
            <span class="client-portal-nav-label">Dashboard</span>
        </a>

        <!-- 2. Projects -->
        <a href="client-projects.php" class="client-portal-nav-item <?php echo ($active_nav === 'projects') ? 'active' : ''; ?>" aria-current="<?php echo ($active_nav === 'projects') ? 'page' : 'false'; ?>">
            <svg class="client-portal-nav-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
            </svg>
            <span class="client-portal-nav-label">Projects</span> 
            <span class="client-portal-nav-chip primary" id="navProjectsCount"><?php echo (int)($shellCounts['projects'] ?? 0); ?></span>
        </a>

        <!-- 3. My Deals -->
        <a href="client-deals.php" class="client-portal-nav-item <?php echo ($active_nav === 'deals') ? 'active' : ''; ?>" aria-current="<?php echo ($active_nav === 'deals') ? 'page' : 'false'; ?>">
            <svg class="client-portal-nav-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M6 3v12"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/>
            </svg>
            <span class="client-portal-nav-label">My Deals</span>
            <span class="client-portal-nav-chip" id="navDealsCount"><?php echo (int)($shellCounts['deals'] ?? 0); ?></span>
        </a>

        <!-- 4. Proposals -->
        <a href="client-proposals.php" class="client-portal-nav-item <?php echo ($active_nav === 'proposals') ? 'active' : ''; ?>" aria-current="<?php echo ($active_nav === 'proposals') ? 'page' : 'false'; ?>">
            <svg class="client-portal-nav-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15l2 2 4-4"/>
            </svg>
            <span class="client-portal-nav-label">Proposals</span>
            <span class="client-portal-nav-chip primary" id="navProposalsCount"><?php echo (int)($shellCounts['proposals'] ?? 0); ?></span>
        </a>

        <!-- 5. Estimates -->
        <a href="client-estimates.php" class="client-portal-nav-item <?php echo ($active_nav === 'estimates') ? 'active' : ''; ?>" aria-current="<?php echo ($active_nav === 'estimates') ? 'page' : 'false'; ?>">
            <svg class="client-portal-nav-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="16" y1="14" x2="16" y2="18"/><path d="M8 10h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/>
            </svg>
            <span class="client-portal-nav-label">Estimates</span>
            <span class="client-portal-nav-chip primary" id="navEstimatesCount"><?php echo (int)($shellCounts['estimates'] ?? 0); ?></span>
        </a>

        <!-- 6. Contracts -->
        <a href="client-contracts.php" class="client-portal-nav-item <?php echo ($active_nav === 'contracts') ? 'active' : ''; ?>" aria-current="<?php echo ($active_nav === 'contracts') ? 'page' : 'false'; ?>">
            <svg class="client-portal-nav-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
            </svg>
            <span class="client-portal-nav-label">Contracts</span>
            <span class="client-portal-nav-chip primary" id="navContractsCount"><?php echo (int)($shellCounts['contracts'] ?? 0); ?></span>
        </a>

        <!-- 7. Invoices -->
        <a href="client-invoices.php" class="client-portal-nav-item <?php echo ($active_nav === 'invoices') ? 'active' : ''; ?>" aria-current="<?php echo ($active_nav === 'invoices') ? 'page' : 'false'; ?>">
            <svg class="client-portal-nav-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
            </svg>
            <span class="client-portal-nav-label">Invoices</span>
            <span class="client-portal-nav-chip warning" id="navInvoicesCount"><?php echo (int)($shellCounts['invoices'] ?? 0); ?></span>
        </a>

        <!-- 3. Tasks -->
        <a href="client-tasks.php" class="client-portal-nav-item <?php echo ($active_nav === 'tasks') ? 'active' : ''; ?>" aria-current="<?php echo ($active_nav === 'tasks') ? 'page' : 'false'; ?>">
            <svg class="client-portal-nav-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
            <span class="client-portal-nav-label">Tasks</span>
            <span class="client-portal-nav-chip warning" id="navTasksCount"><?php echo (int)($shellCounts['tasks'] ?? 0); ?></span>
        </a>

        <!-- 4. Calendar -->
        <a href="client-calendar.php" class="client-portal-nav-item <?php echo ($active_nav === 'calendar') ? 'active' : ''; ?>" aria-current="<?php echo ($active_nav === 'calendar') ? 'page' : 'false'; ?>">
            <svg class="client-portal-nav-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <span class="client-portal-nav-label">Calendar</span>
        </a>

        <!-- 5. Messages -->
        <a href="client-messages.php" class="client-portal-nav-item <?php echo ($active_nav === 'messages') ? 'active' : ''; ?>" aria-current="<?php echo ($active_nav === 'messages') ? 'page' : 'false'; ?>">
            <svg class="client-portal-nav-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <span class="client-portal-nav-label">Messages</span>
            <span class="client-portal-nav-chip primary" id="navMessagesCount"><?php echo (int)($shellCounts['messages'] ?? 0); ?></span>
        </a>

        <!-- 6. Documents -->
        <a href="client-documents.php" class="client-portal-nav-item <?php echo ($active_nav === 'documents') ? 'active' : ''; ?>" aria-current="<?php echo ($active_nav === 'documents') ? 'page' : 'false'; ?>">
            <svg class="client-portal-nav-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            <span class="client-portal-nav-label">Documents</span>
        </a>

        <div class="client-portal-nav-group-label" style="margin-top:16px;">ACCOUNT &amp; SUPPORT</div>

        <!-- 7. Profile -->
        <a href="client-profile.php" class="client-portal-nav-item <?php echo ($active_nav === 'profile') ? 'active' : ''; ?>" aria-current="<?php echo ($active_nav === 'profile') ? 'page' : 'false'; ?>">
            <svg class="client-portal-nav-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
            <span class="client-portal-nav-label">Profile</span>
        </a>

        <!-- 8. Help & Support -->
        <a href="client-help.php" class="client-portal-nav-item <?php echo ($active_nav === 'help') ? 'active' : ''; ?>" aria-current="<?php echo ($active_nav === 'help') ? 'page' : 'false'; ?>">
            <svg class="client-portal-nav-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <span class="client-portal-nav-label">Help &amp; Support</span>
        </a>
    </nav>

    <!-- Sidebar Bottom User Card & Quick Logout -->
    <div class="client-portal-sidebar-footer">
        <div class="client-portal-user-mini">
            <div class="client-portal-avatar" id="sidebarUserAvatar" style="background-color: #2563EB;"><?php echo htmlspecialchars($client['avatar_initials'] ?? 'CP'); ?></div>
            <div class="client-portal-user-info">
                <div class="client-portal-user-name" id="sidebarUserName"><?php echo htmlspecialchars($client['contact_name'] ?? 'Client User'); ?></div>
                <div class="client-portal-user-role" id="sidebarUserRole"><?php echo htmlspecialchars(!empty($client['job_title']) ? $client['job_title'] : 'Client Contact'); ?></div>
            </div>
            <button type="button" class="client-portal-logout-quick-btn" title="Sign out" aria-label="Sign out" onclick="window.clientPortal.logout()">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </button>
        </div>
    </div>
</aside>
