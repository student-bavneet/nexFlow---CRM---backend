<?php
// Shared Sidebar Partial
$current_script = basename($_SERVER['PHP_SELF'], '.php');
$active_id = ($current_script === 'index') ? 'dashboard' : $current_script;
if (isset($current_page) && !empty($current_page)) {
    $active_id = $current_page;
}

$leadsCount = 0;
$tasksCount = 0;
$inboxUnreadCount = 0;
if (function_exists('nexflow_current_user')) {
    $sbUser = nexflow_current_user();
    if ($sbUser && !empty($sbUser['organization_id'])) {
        try {
            $sbPdo = nexflow_db();
            $sbOrgId = (int)$sbUser['organization_id'];
            $sbUid = (int)$sbUser['id'];

            // 1. Leads: active (non-archived) leads
            if (!function_exists('canView') || canView('leads', $sbUid)) {
                $leadStmt = $sbPdo->prepare("SELECT COUNT(*) FROM leads WHERE organization_id = ? AND is_archived = 0");
                $leadStmt->execute([$sbOrgId]);
                $leadsCount = (int)$leadStmt->fetchColumn();
            }

            // 2. Tasks: incomplete (non-completed) tasks
            if (!function_exists('canView') || canView('tasks', $sbUid)) {
                $taskStmt = $sbPdo->prepare("SELECT COUNT(*) FROM tasks WHERE organization_id = ? AND status != 'completed'");
                $taskStmt->execute([$sbOrgId]);
                $tasksCount = (int)$taskStmt->fetchColumn();
            }

            // 3. Inbox: unread non-archived non-snoozed conversations
            if (!function_exists('canView') || canView('inbox', $sbUid)) {
                $inboxStmt = $sbPdo->prepare("SELECT COUNT(*) FROM inbox_conversations WHERE organization_id = ? AND is_unread = 1 AND status != 'Archived' AND (snoozed_until IS NULL OR snoozed_until <= NOW())");
                $inboxStmt->execute([$sbOrgId]);
                $inboxUnreadCount = (int)$inboxStmt->fetchColumn();
            }
        } catch (Throwable $e) {}
    }
}

$nav_items_main = [
    ['id' => 'dashboard', 'label' => 'Dashboard', 'url' => 'index.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z"/></svg>'],
    ['id' => 'leads', 'label' => 'Leads', 'url' => 'leads.php', 'badge' => ($leadsCount > 0 ? $leadsCount : null), 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>'],
    ['id' => 'pipeline', 'label' => 'Sales Pipeline', 'url' => 'pipeline.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 3v12"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 01-9 9"/></svg>'],
    ['id' => 'projects', 'label' => 'Projects', 'url' => 'projects.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>'],
    ['id' => 'contacts', 'label' => 'Contacts', 'url' => 'contacts.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'],
    ['id' => 'companies', 'label' => 'Companies', 'url' => 'companies.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01"/></svg>'],
    ['id' => 'subscriptions', 'label' => 'Subscriptions', 'url' => 'subscriptions.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/><path d="M7 15h.01M11 15h2"/></svg>'],
    ['id' => 'expenses', 'label' => 'Expenses', 'url' => 'expenses.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>'],
    ['id' => 'invoices', 'label' => 'Invoices', 'url' => 'invoices.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16l3-2 3 2 3-2 3 2 4-2.5V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>'],
    ['id' => 'contracts', 'label' => 'Contracts', 'url' => 'contracts.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15l2 2 4-4"/></svg>'],
    ['id' => 'proposals', 'label' => 'Proposals', 'url' => 'proposals.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>'],
    ['id' => 'documents', 'label' => 'Documents', 'url' => 'documents.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>'],
    ['id' => 'estimate-requests', 'label' => 'Estimate Requests', 'url' => 'estimate-requests.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="16" y1="14" x2="16" y2="18"/><path d="M16 10h.01M12 10h.01M8 10h.01M12 14h.01M8 14h.01M12 18h.01M8 18h.01"/></svg>'],
    ['id' => 'tasks', 'label' => 'Tasks', 'url' => 'tasks.php', 'badge' => ($tasksCount > 0 ? $tasksCount : null), 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>'],
    ['id' => 'calendar', 'label' => 'Calendar', 'url' => 'calendar.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'],
    ['id' => 'inbox', 'label' => 'Inbox', 'url' => 'inbox.php', 'badge' => ($inboxUnreadCount > 0 ? $inboxUnreadCount : null), 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>']
];

$nav_items_bottom = [
    ['id' => 'reports', 'label' => 'Reports', 'url' => 'reports.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>'],
    ['id' => 'team', 'label' => 'Team', 'url' => 'team.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>'],
    ['id' => 'settings', 'label' => 'Settings', 'url' => 'settings.php', 'icon' => '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>']
];
?>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo-icon">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
            </svg>
        </div>
        <div class="sidebar-logo-text">
            <span class="sidebar-brand-name">NexFlow</span>
            <span class="sidebar-brand-tag">CRM</span>
        </div>
    </div>
    
    <nav class="sidebar-nav scrollbar-hide">
        <?php foreach ($nav_items_main as $item): ?>
            <?php if (!canView($item['id'])) continue; ?>
            <?php $active = ($active_id === $item['id']); ?>
            <a href="<?php echo $item['url']; ?>" class="sidebar-nav-item <?php echo $active ? 'active' : ''; ?>">
                <?php echo $item['icon']; ?>
                <span class="sidebar-nav-label"><?php echo htmlspecialchars($item['label']); ?></span>
                <?php if (in_array($item['id'], ['leads', 'tasks', 'inbox'])): ?>
                    <span class="sidebar-badge" data-sidebar-badge="<?php echo htmlspecialchars($item['id']); ?>" style="<?php echo (isset($item['badge']) && $item['badge'] > 0) ? '' : 'display:none;'; ?>"><?php echo isset($item['badge']) ? (int)$item['badge'] : 0; ?></span>
                <?php elseif (isset($item['badge'])): ?>
                    <span class="sidebar-badge"><?php echo $item['badge']; ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
        
        <div class="sidebar-divider"></div>
        
        <?php foreach ($nav_items_bottom as $item): ?>
            <?php if (!canView($item['id'])) continue; ?>
            <?php $active = ($active_id === $item['id']); ?>
            <a href="<?php echo $item['url']; ?>" class="sidebar-nav-item <?php echo $active ? 'active' : ''; ?>">
                <?php echo $item['icon']; ?>
                <span class="sidebar-nav-label"><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <div class="sidebar-footer">
        <?php if (canView('help')): ?>
        <a href="help.php" class="sidebar-nav-item <?php echo ($active_id === 'help') ? 'active' : ''; ?>">
            <svg class="sidebar-nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <span class="sidebar-nav-label">Help & Support</span>
        </a>
        <?php endif; ?>
        
        <?php
        if (!isset($user) || !is_array($user)) {
            require_once __DIR__ . '/auth.php';
            $user = nexflow_current_user() ?? [];
        }

        $displayUserName = !empty($user['name']) ? $user['name'] : 'Olivia Carter';
        $displayUserRole = !empty($user['role_label']) ? $user['role_label'] : nexflow_role_label($user['role_id'] ?? $user['role'] ?? 'super_admin');
        $nameParts = explode(' ', trim($displayUserName));
        $userInitials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
        $userPhotoUrl = (!empty($user['photo_path']) && file_exists(__DIR__ . '/../../' . ltrim($user['photo_path'], '/'))) ? ('/nexFlow/' . ltrim($user['photo_path'], '/')) : null;
        ?>

        <div class="sidebar-profile" onclick="showConfirmModal({ title: 'Sign Out', message: 'Are you sure you want to sign out of your NexFlow CRM workspace?', confirmText: 'Sign Out', type: 'danger', onConfirm: function() { window.location.href = '/nexFlow/admin/logout.php'; } })" style="cursor: pointer;">
            <?php if ($userPhotoUrl): ?>
                <img src="<?php echo htmlspecialchars($userPhotoUrl); ?>" alt="<?php echo htmlspecialchars($displayUserName); ?>" class="avatar avatar-sm sidebar-user-photo" style="object-fit: cover; border-radius: 50%; width: 32px; height: 32px;">
            <?php else: ?>
                <div class="avatar avatar-sm sidebar-user-avatar" style="background-color: #7C3AED;"><?php echo htmlspecialchars($userInitials); ?></div>
            <?php endif; ?>
            <div class="sidebar-profile-info">
                <div class="sidebar-profile-name"><?php echo htmlspecialchars($displayUserName); ?></div>
                <div class="sidebar-profile-role"><?php echo htmlspecialchars($displayUserRole); ?></div>
            </div>
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color: #667085; flex-shrink:0;" title="Sign Out">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
        </div>
    </div>
</aside>
