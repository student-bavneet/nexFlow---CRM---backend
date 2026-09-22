<?php
// Shared Header Partial
require_once __DIR__ . '/auth.php';
$user = require_admin_auth();
require_once __DIR__ . '/permissions.php';

if (!isset($page_title)) {
    $page_title = "NexFlow CRM";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> — NexFlow CRM</title>
    <meta name="description" content="NexFlow CRM - Modern Sales & Lead Management Platform">
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js 4.x CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Application Base Stylesheets -->
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/table-columns.css">
    <link rel="stylesheet" href="assets/css/projects.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/softphone.css">
    <link rel="stylesheet" href="assets/css/sticky-notes.css">
    <link rel="stylesheet" href="assets/css/time-tracker.css">
    <link rel="stylesheet" href="assets/css/share-modal.css">
    <link rel="stylesheet" href="assets/css/global-search.css">
    <script>
        window.NexFlowCurrentPageKey = <?php echo json_encode($current_page ?? 'dashboard'); ?>;
        window.userPermissions = <?php echo json_encode(get_user_permissions()); ?>;
        window.hasPermission = function(module, action) {
            action = (action || 'view').toLowerCase();
            if (window.userPermissions && window.userPermissions.full_access) return true;
            const perms = (window.userPermissions && window.userPermissions.permissions) ? window.userPermissions.permissions[module] : null;
            if (!perms) return false;
            return Array.isArray(perms) && perms.map(a => a.toLowerCase()).includes(action);
        };
    </script>
    <script src="assets/js/universal-crm.js"></script>

    <!-- Page-Specific Stylesheets -->
    <?php if (isset($current_page) && $current_page === 'leads'): ?>
    <link rel="stylesheet" href="assets/css/leads.css?v=<?= filemtime(__DIR__ . '/../assets/css/leads.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'projects'): ?>
    <link rel="stylesheet" href="assets/css/projects.css">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'documents'): ?>
    <link rel="stylesheet" href="assets/css/documents.css">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'contacts'): ?>
    <link rel="stylesheet" href="assets/css/contacts.css">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'companies'): ?>
    <link rel="stylesheet" href="assets/css/companies.css">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'subscriptions'): ?>
    <link rel="stylesheet" href="assets/css/subscriptions.css?v=<?= filemtime(__DIR__ . '/../assets/css/subscriptions.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'expenses'): ?>
    <link rel="stylesheet" href="assets/css/expenses.css?v=<?= filemtime(__DIR__ . '/../assets/css/expenses.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'invoices'): ?>
    <link rel="stylesheet" href="assets/css/invoices.css?v=<?= filemtime(__DIR__ . '/../assets/css/invoices.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'contracts'): ?>
    <link rel="stylesheet" href="assets/css/contracts.css?v=<?= filemtime(__DIR__ . '/../assets/css/contracts.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'proposals'): ?>
    <link rel="stylesheet" href="assets/css/proposals.css?v=<?= filemtime(__DIR__ . '/../assets/css/proposals.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'estimate-requests'): ?>
    <link rel="stylesheet" href="assets/css/estimate-requests.css?v=<?= filemtime(__DIR__ . '/../assets/css/estimate-requests.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'tasks'): ?>
    <link rel="stylesheet" href="assets/css/tasks.css?v=<?= filemtime(__DIR__ . '/../assets/css/tasks.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'calendar'): ?>
    <link rel="stylesheet" href="assets/css/calendar.css?v=<?= filemtime(__DIR__ . '/../assets/css/calendar.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'inbox'): ?>
    <link rel="stylesheet" href="assets/css/inbox.css?v=<?= filemtime(__DIR__ . '/../assets/css/inbox.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'reports'): ?>
    <link rel="stylesheet" href="assets/css/reports.css?v=<?= filemtime(__DIR__ . '/../assets/css/reports.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'team'): ?>
    <link rel="stylesheet" href="assets/css/team.css?v=<?= filemtime(__DIR__ . '/../assets/css/team.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'settings'): ?>
    <link rel="stylesheet" href="assets/css/settings.css?v=<?= filemtime(__DIR__ . '/../assets/css/settings.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'help'): ?>
    <link rel="stylesheet" href="assets/css/help.css?v=<?= filemtime(__DIR__ . '/../assets/css/help.css') ?>">
    <?php endif; ?>
    <?php if (isset($current_page) && $current_page === 'pipeline'): ?>
    <link rel="stylesheet" href="assets/css/pipeline.css?v=<?= filemtime(__DIR__ . '/../assets/css/pipeline.css') ?>">
    <?php endif; ?>

    <!-- Master Theme Stylesheet (Loaded Last For Theme Precedence) -->
    <link rel="stylesheet" href="assets/css/theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/theme.css') ?>">
</head>
<body>
<div class="app-wrapper">

