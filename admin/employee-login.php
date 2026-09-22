<?php
$page_title = "Employee Login — NexFlow CRM";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="NexFlow CRM - Employee & Team Workspace Sign In">
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Application Stylesheets -->
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/employee-login.css">
</head>
<body>

<div class="employee-login-page">

    <!-- LEFT BRAND PANEL (~44% Desktop Width) -->
    <aside class="employee-login-left">
        <div class="employee-login-left-bg-glow"></div>
        <div class="employee-login-left-grid-pattern"></div>

        <!-- Left Header & Logo -->
        <div class="employee-login-left-header">
            <a href="employee-login.php" class="employee-login-logo">
                <div class="employee-login-logo-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <div class="employee-login-logo-text">
                    <span class="employee-login-brand-name">NexFlow</span>
                    <span class="employee-login-brand-tag">CRM</span>
                </div>
            </a>
        </div>

        <!-- Left Body & Hero Content -->
        <div class="employee-login-left-body">
            <h1 class="employee-login-hero-heading">Your sales workspace, always within reach.</h1>
            <p class="employee-login-hero-subtext">Manage leads, follow up with customers, collaborate with your team, and keep every opportunity moving.</p>

            <!-- Three Compact Benefits -->
            <div class="employee-login-benefits-list">
                <div class="employee-login-benefit-item">
                    <div class="employee-login-benefit-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </div>
                    <div class="employee-login-benefit-text">
                        <strong>Manage leads and deals</strong><br>
                        Qualify prospects and track deals through pipeline stages.
                    </div>
                </div>

                <div class="employee-login-benefit-item">
                    <div class="employee-login-benefit-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
                        </svg>
                    </div>
                    <div class="employee-login-benefit-text">
                        <strong>Connect through calls, WhatsApp and email</strong><br>
                        Reach clients instantly with integrated communication tools.
                    </div>
                </div>

                <div class="employee-login-benefit-item">
                    <div class="employee-login-benefit-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                        </svg>
                    </div>
                    <div class="employee-login-benefit-text">
                        <strong>Track tasks and customer conversations</strong><br>
                        Stay organized with daily task lists and unified inbox streams.
                    </div>
                </div>
            </div>

            <!-- Compact Workplace Status Card -->
            <div class="employee-login-status-card">
                <div class="employee-login-status-dot"></div>
                <div>
                    <div class="employee-login-status-title">NexFlow Workspace</div>
                    <div class="employee-login-status-sub">Sales team systems available</div>
                </div>
            </div>
        </div>

        <!-- Left Footer -->
        <div class="employee-login-left-footer">
            NexFlow CRM v2.4 • Employee Portal
        </div>
    </aside>

    <!-- RIGHT LOGIN SECTION (~56% Desktop Width) -->
    <main class="employee-login-right">

        <!-- Mobile Header & Logo (Visible only on Mobile) -->
        <a href="employee-login.php" class="employee-login-mobile-logo">
            <div class="employee-login-logo-icon">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
            </div>
            <div class="employee-login-logo-text">
                <span class="employee-login-brand-name" style="color:var(--text-heading);">NexFlow</span>
                <span class="employee-login-brand-tag" style="color:var(--text-secondary);">CRM</span>
            </div>
        </a>

        <!-- Centered Login Card -->
        <div class="employee-login-card">
            <div class="employee-login-card-header">
                <div class="employee-login-badge">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                    Employee Workspace
                </div>
                <h2 class="employee-login-title">Welcome back</h2>
                <p class="employee-login-subtitle">Sign in to continue to your NexFlow workspace.</p>
            </div>

            <form id="employeeLoginForm" novalidate>
                
                <!-- Work Email Field -->
                <div class="employee-login-form-group" id="empEmailGroup">
                    <label class="employee-login-label" for="employeeEmail">Work Email</label>
                    <div class="employee-login-input-wrapper">
                        <svg class="employee-login-input-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <input type="email" class="employee-login-input" id="employeeEmail" name="email" value="sarah.chen@NexFlow.io" placeholder="name@company.com" required autocomplete="email">
                    </div>
                    <div class="employee-login-error-msg"></div>
                </div>

                <!-- Password Field -->
                <div class="employee-login-form-group" id="empPassGroup">
                    <label class="employee-login-label" for="employeePassword">Password</label>
                    <div class="employee-login-input-wrapper">
                        <svg class="employee-login-input-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                        <input type="password" class="employee-login-input has-toggle" id="employeePassword" name="password" value="••••••••••••" placeholder="Enter your password" required autocomplete="current-password">
                        
                        <button type="button" class="employee-login-toggle-pass" id="toggleEmployeePasswordBtn" aria-label="Show password">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <div class="employee-login-caps-warning" id="employeeCapsLockWarning">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Caps Lock is ON
                    </div>
                    <div class="employee-login-error-msg"></div>
                </div>

                <!-- Options Row -->
                <div class="employee-login-options-row">
                    <label class="employee-login-checkbox-label">
                        <input type="checkbox" class="employee-login-checkbox" id="rememberEmployeeDevice" checked>
                        Remember this device
                    </label>
                    <a href="#" class="employee-login-forgot-link" id="forgotPasswordLink">Forgot password?</a>
                </div>

                <!-- Full-Width Primary Sign In Button -->
                <button type="submit" class="btn btn-primary btn-lg employee-login-submit-btn" id="employeeSubmitBtn">
                    <span class="employee-login-btn-text">Sign in to Workspace</span>
                    <span class="employee-login-spinner"></span>
                </button>

                <!-- Divider -->
                <div class="employee-login-divider">
                    <span>or</span>
                </div>

                <!-- Secondary Outlined Demo Access Button -->
                <button type="button" class="btn btn-secondary btn-md employee-login-demo-btn" id="useDemoAccessBtn">
                    Use Demo Employee Access
                </button>

                <!-- Demo Notice Box -->
                <div class="employee-login-demo-notice-box" id="employeeDemoNoticeBox">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>
                        <strong>Frontend Demo Credentials:</strong> Demo credentials are for frontend preview only.
                    </div>
                </div>
            </form>

            <!-- Card Footer -->
            <div class="employee-login-card-footer">
                Having trouble signing in? <button type="button" class="employee-login-admin-help-btn" id="adminHelpBtn">Contact your workspace administrator</button>
            </div>
        </div>

    </main>
</div>

<!-- FORGOT PASSWORD MODAL (Two-Step View) -->
<div class="employee-login-modal-overlay" id="forgotEmployeePasswordModal" role="dialog" aria-modal="true" aria-labelledby="forgotEmployeeModalTitle">
    <div class="employee-login-modal">
        <div class="employee-login-modal-header">
            <h3 class="employee-login-modal-title" id="forgotEmployeeModalTitle">Reset your password</h3>
            <button type="button" class="employee-login-modal-close" id="closeForgotEmployeeModalBtn" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Step 1: Initial Form View -->
        <div id="forgotModalInitialView">
            <form id="forgotEmployeePasswordForm" novalidate>
                <div class="employee-login-modal-body">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                        <div style="width:36px;height:36px;border-radius:var(--radius-md);background-color:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        </div>
                        <p style="font-size:13.5px;color:var(--text-secondary);line-height:1.4;margin:0;">
                            Enter your work email and we'll send password reset instructions.
                        </p>
                    </div>

                    <div class="employee-login-form-group" id="forgotEmpEmailGroup" style="margin-bottom:0;">
                        <label class="employee-login-label" for="forgotEmployeeEmailInput">Work Email</label>
                        <div class="employee-login-input-wrapper">
                            <svg class="employee-login-input-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                            </svg>
                            <input type="email" class="employee-login-input" id="forgotEmployeeEmailInput" placeholder="name@company.com" required>
                        </div>
                        <div class="employee-login-error-msg"></div>
                    </div>
                </div>
                <div class="employee-login-modal-footer">
                    <button type="button" class="btn btn-secondary btn-md" id="cancelForgotEmployeeModalBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-md">Send Instructions</button>
                </div>
            </form>
        </div>

        <!-- Step 2: Success Notice View -->
        <div id="forgotModalSuccessView" style="display:none;">
            <div class="employee-login-modal-body" style="text-align:center;padding:32px 24px;">
                <div class="employee-login-modal-success-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h4 style="font-size:18px;font-weight:700;color:var(--text-heading);margin-bottom:6px;">Check your email</h4>
                <p style="font-size:13.5px;color:var(--text-secondary);max-width:360px;margin:0 auto 16px;line-height:1.5;">
                    Frontend demonstration only — no password reset email was sent.
                </p>
            </div>
            <div class="employee-login-modal-footer" style="justify-content:center;">
                <button type="button" class="btn btn-primary btn-md" id="returnToLoginBtn">Return to Login</button>
            </div>
        </div>

    </div>
</div>

<!-- ADMIN HELP POPOVER MODAL -->
<div class="employee-login-popover-overlay" id="adminHelpPopoverModal" role="dialog" aria-modal="true" aria-labelledby="adminHelpPopoverTitle">
    <div class="employee-login-modal">
        <div class="employee-login-modal-header">
            <h3 class="employee-login-modal-title" id="adminHelpPopoverTitle">Workspace Administrator Help</h3>
            <button type="button" class="employee-login-modal-close" id="closeAdminHelpPopoverBtn" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="employee-login-modal-body">
            <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:16px;">
                <div style="width:36px;height:36px;border-radius:var(--radius-md);background-color:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
                </div>
                <div>
                    <h4 style="font-size:15px;font-weight:600;color:var(--text-heading);margin-bottom:4px;">Need Account Access?</h4>
                    <p style="font-size:13.5px;color:var(--text-secondary);line-height:1.4;margin:0;">
                        Contact your NexFlow administrator for account access.
                    </p>
                </div>
            </div>

            <div class="employee-login-demo-notice-box show" style="margin-top:0;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <div>
                    No support request will be submitted in this frontend demo.
                </div>
            </div>
        </div>
        <div class="employee-login-modal-footer">
            <button type="button" class="btn btn-primary btn-md" id="okAdminHelpPopoverBtn">Got It</button>
        </div>
    </div>
</div>

<!-- Global Application Scripts -->
<script src="assets/js/employee-login.js"></script>

</body>
</html>

