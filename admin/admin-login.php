<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $setupComplete = (bool)nexflow_db()->query("SELECT id FROM organizations WHERE setup_completed = 1 LIMIT 1")->fetchColumn();
} catch (Throwable $e) {
    $setupComplete = false;
}

if (!$setupComplete) {
    header('Location: onboarding.php');
    exit;
}

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$page_title = "Admin Login — NexFlow CRM";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="NexFlow CRM - Admin Workspace Sign In">
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Application Stylesheets -->
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/admin-login.css">
</head>
<body>

<div class="admin-login-page">

    <!-- LEFT BRAND PANEL (~45% Desktop Width) -->
    <aside class="admin-login-left">
        <div class="admin-login-left-bg-glow"></div>
        <div class="admin-login-left-grid-pattern"></div>

        <!-- Left Header & Logo -->
        <div class="admin-login-left-header">
            <a href="admin-login.php" class="admin-login-logo">
                <div class="admin-login-logo-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <div class="admin-login-logo-text">
                    <span class="admin-login-brand-name">NexFlow</span>
                    <span class="admin-login-brand-tag">CRM</span>
                </div>
            </a>
        </div>

        <!-- Left Body & Hero Content -->
        <div class="admin-login-left-body">
            <h1 class="admin-login-hero-heading">Manage your sales operation from one place.</h1>
            <p class="admin-login-hero-subtext">Streamline lead management, track pipeline deals, and empower your team with unified customer communication.</p>

            <!-- Three Compact Benefits -->
            <div class="admin-login-benefits-list">
                <div class="admin-login-benefit-item">
                    <div class="admin-login-benefit-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
                        </svg>
                    </div>
                    <div class="admin-login-benefit-text">
                        <strong>Organize leads and contacts</strong><br>
                        Centralize customer data, lead scoring, and activity history.
                    </div>
                </div>

                <div class="admin-login-benefit-item">
                    <div class="admin-login-benefit-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M6 3v12"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 01-9 9"/>
                        </svg>
                    </div>
                    <div class="admin-login-benefit-text">
                        <strong>Track every deal</strong><br>
                        Visualize kanban sales pipeline stages and revenue forecasts.
                    </div>
                </div>

                <div class="admin-login-benefit-item">
                    <div class="admin-login-benefit-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
                        </svg>
                    </div>
                    <div class="admin-login-benefit-text">
                        <strong>Connect through calls, WhatsApp and email</strong><br>
                        Integrated Softphone dialer, wa.me messaging, and unified inbox.
                    </div>
                </div>
            </div>

            <!-- Minimal CRM Insight Card -->
            <div class="admin-login-insight-card">
                <div class="admin-login-insight-icon">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                </div>
                <div>
                    <div class="admin-login-insight-val">$1.24M Active Pipeline</div>
                    <div class="admin-login-insight-sub">Across 56 active qualified sales deals</div>
                </div>
            </div>
        </div>

        <!-- Left Footer -->
        <div class="admin-login-left-footer">
            NexFlow CRM v2.4 • Admin Portal
        </div>
    </aside>

    <!-- RIGHT LOGIN SECTION (~55% Desktop Width) -->
    <main class="admin-login-right">

        <!-- Mobile Header & Logo (Visible only on Mobile) -->
        <a href="admin-login.php" class="admin-login-mobile-logo">
            <div class="admin-login-logo-icon">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
            </div>
            <div class="admin-login-logo-text">
                <span class="admin-login-brand-name" style="color:var(--text-heading);">NexFlow</span>
                <span class="admin-login-brand-tag" style="color:var(--text-secondary);">CRM</span>
            </div>
        </a>

        <!-- Modern Centered Login Card -->
        <div class="admin-login-card">
            <div class="admin-login-card-header">
                <h2 class="admin-login-title">Welcome back</h2>
                <p class="admin-login-subtitle">Sign in to your NexFlow admin workspace.</p>
            </div>

            <form id="adminLoginForm" novalidate>
                
                <!-- Email Field -->
                <div class="admin-login-form-group" id="adminEmailGroup">
                    <label class="admin-login-label" for="adminEmail">Admin Email Address</label>
                    <div class="admin-login-input-wrapper">
                        <svg class="admin-login-input-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <input type="email" class="admin-login-input" id="adminEmail" name="email" value="" placeholder="admin@company.com" required autocomplete="username">
                    </div>
                    <div class="admin-login-error-msg"></div>
                </div>

                <!-- Password Field -->
                <div class="admin-login-form-group" id="adminPassGroup">
                    <label class="admin-login-label" for="adminPassword">Password</label>
                    <div class="admin-login-input-wrapper">
                        <svg class="admin-login-input-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                        <input type="password" class="admin-login-input has-toggle" id="adminPassword" name="password" value="" placeholder="Enter your password" required autocomplete="current-password">
                        
                        <button type="button" class="admin-login-toggle-pass" id="togglePasswordBtn" aria-label="Show password">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <div class="admin-login-caps-warning" id="capsLockWarning">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Caps Lock is ON
                    </div>
                    <div class="admin-login-error-msg"></div>
                </div>

                <!-- Options Row -->
                <div class="admin-login-options-row">
                    <label class="admin-login-checkbox-label">
                        <input type="checkbox" class="admin-login-checkbox" id="rememberDevice" checked>
                        Remember this device
                    </label>
                    <a href="#" class="admin-login-forgot-link" id="forgotPasswordLink">Forgot password?</a>
                </div>

                <!-- Full-Width Submit Button -->
                <button type="submit" class="btn btn-primary btn-lg admin-login-submit-btn" id="adminSubmitBtn">
                    <span class="admin-login-btn-text">Sign in to NexFlow</span>
                    <span class="admin-login-spinner"></span>
                </button>
            </form>

            <!-- Card Footer & Demo Notice -->
            <div class="admin-login-card-footer">
                <div class="admin-login-authorized-tag">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    Authorized administrators only
                </div>

                <div class="admin-login-demo-banner">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>
                        <strong>Frontend Demo Login:</strong> Click "Sign in" to test form validation and simulate workspace entry to the Dashboard.
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- FORGOT PASSWORD MODAL -->
<div class="admin-login-modal-overlay" id="forgotPasswordModal" role="dialog" aria-modal="true" aria-labelledby="forgotModalTitle">
    <div class="admin-login-modal">
        <div class="admin-login-modal-header">
            <h3 class="admin-login-modal-title" id="forgotModalTitle">Reset Password</h3>
            <button type="button" class="admin-login-modal-close" id="closeForgotModalBtn" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="forgotPasswordForm" novalidate>
            <div class="admin-login-modal-body">
                <p style="font-size:13.5px;color:var(--text-secondary);margin-bottom:16px;line-height:1.4;">
                    Enter your administrator email address below to receive password reset instructions.
                </p>
                
                <div class="admin-login-form-group" id="forgotEmailGroup" style="margin-bottom:0;">
                    <label class="admin-login-label" for="forgotEmailInput">Admin Email Address</label>
                    <div class="admin-login-input-wrapper">
                        <svg class="admin-login-input-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <input type="email" class="admin-login-input" id="forgotEmailInput" placeholder="admin@company.com" required>
                    </div>
                    <div class="admin-login-error-msg"></div>
                </div>

                <div class="admin-login-demo-notice" id="forgotDemoNotice">
                    📌 <strong>Demo only</strong> — no reset email was sent.
                </div>
            </div>
            <div class="admin-login-modal-footer">
                <button type="button" class="btn btn-secondary btn-md" id="cancelForgotModalBtn">Cancel</button>
                <button type="submit" class="btn btn-primary btn-md">Send reset instructions</button>
            </div>
        </form>
    </div>
</div>

<!-- Global Application Scripts -->
<script src="assets/js/admin-login.js"></script>

</body>
</html>

