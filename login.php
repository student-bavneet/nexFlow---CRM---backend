<?php
require_once __DIR__ . '/includes/client-auth.php';
$return_to = sanitize_client_return_url($_GET['return_to'] ?? null);
if (client_current_user() !== null) {
    header('Location: ' . ($return_to ?: 'client-dashboard.php'));
    exit;
}
$page_title = "Client Login — NexFlow CRM";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="NexFlow CRM - Client Portal Sign In">
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Application Stylesheets -->
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/client-login.css">
</head>
<body>

<div class="client-login-page">

    <!-- LEFT BRAND PANEL (~48% Desktop Width) -->
    <aside class="client-login-left">
        <div class="client-login-left-bg-glow"></div>
        <div class="client-login-left-grid-pattern"></div>

        <!-- Left Header & Logo -->
        <div class="client-login-left-header">
            <a href="client-login.php" class="client-login-logo">
                <div class="client-login-logo-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                    </svg>
                </div>
                <div class="client-login-logo-text">
                    <span class="client-login-brand-name">NexFlow</span>
                    <span class="client-login-brand-tag">Client Portal</span>
                </div>
            </a>
        </div>

        <!-- Left Body & Hero Content -->
        <div class="client-login-left-body">
            <h1 class="client-login-hero-heading">Everything you need, in one secure workspace.</h1>
            <p class="client-login-hero-subtext">Stay connected with your team, follow progress, review shared files, and keep every conversation organized.</p>

            <!-- Three Modern Benefit Rows -->
            <div class="client-login-benefits-list">
                <div class="client-login-benefit-item">
                    <div class="client-login-benefit-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/>
                        </svg>
                    </div>
                    <div class="client-login-benefit-text">
                        <strong>Follow project and service updates</strong><br>
                        Track project milestones, status updates, and timeline progress.
                    </div>
                </div>

                <div class="client-login-benefit-item">
                    <div class="client-login-benefit-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                    </div>
                    <div class="client-login-benefit-text">
                        <strong>View conversations and shared documents</strong><br>
                        Access project files, meeting notes, and team messages.
                    </div>
                </div>

                <div class="client-login-benefit-item">
                    <div class="client-login-benefit-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </div>
                    <div class="client-login-benefit-text">
                        <strong>Manage your account information</strong><br>
                        Update contact details, security settings, and notifications.
                    </div>
                </div>
            </div>

            <!-- Portal Preview Card (Fictional Sample Content) -->
            <div class="client-login-portal-preview-card">
                <div class="client-login-preview-header">
                    <span class="client-login-preview-title">Account Overview</span>
                    <span class="client-login-preview-badge">● On Track</span>
                </div>
                <div class="client-login-preview-row">
                    <span>Next Scheduled Update</span>
                    <strong style="color:#FFFFFF;">Thursday, 10:30 AM</strong>
                </div>

                <div class="client-login-progress-group">
                    <div class="client-login-progress-label">
                        <span>Project Deliverables</span>
                        <strong>85%</strong>
                    </div>
                    <div class="client-login-progress-bar">
                        <div class="client-login-progress-fill" style="width: 85%;"></div>
                    </div>
                </div>

                <div class="client-login-progress-group">
                    <div class="client-login-progress-label">
                        <span>Shared Documents</span>
                        <strong>12 files available</strong>
                    </div>
                    <div class="client-login-progress-bar">
                        <div class="client-login-progress-fill" style="width: 100%; background-color:#60A5FA;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Left Footer -->
        <div class="client-login-left-footer">
            Powered by NexFlow CRM
        </div>
    </aside>

    <!-- RIGHT LOGIN AREA (~52% Desktop Width) -->
    <main class="client-login-right">

        <!-- Mobile Header & Logo (Visible only on Mobile) -->
        <a href="client-login.php" class="client-login-mobile-logo">
            <div class="client-login-logo-icon">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
            </div>
            <div class="client-login-logo-text">
                <span class="client-login-brand-name" style="color:var(--text-heading);">NexFlow</span>
                <span class="client-login-brand-tag" style="color:var(--primary);">Client Portal</span>
            </div>
        </a>

        <!-- Centered Login Card -->
        <div class="client-login-card">
            <div class="client-login-card-header">
                <div class="client-login-badge">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                    </svg>
                    Client Portal
                </div>
                <h2 class="client-login-title">Welcome back</h2>
                <p class="client-login-subtitle">Enter your details to access your client workspace.</p>
            </div>

            <!-- LOGIN FORM VIEW -->
            <form id="clientLoginForm" novalidate>
                <input type="hidden" id="clientCsrfToken" name="csrf_token" value="<?php echo htmlspecialchars(client_csrf_token()); ?>">
                <input type="hidden" id="clientReturnTo" name="return_to" value="<?php echo htmlspecialchars($return_to ?? ''); ?>">
                
                <!-- Email Field -->
                <div class="client-login-form-group" id="clientEmailGroup">
                    <label class="client-login-label" for="clientEmail">Email Address</label>
                    <div class="client-login-input-wrapper">
                        <svg class="client-login-input-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <input type="email" class="client-login-input" id="clientEmail" name="email" value="" placeholder="you@company.com" required autocomplete="email">
                    </div>
                    <div class="client-login-error-msg"></div>
                </div>

                <!-- Password Field -->
                <div class="client-login-form-group" id="clientPassGroup">
                    <label class="client-login-label" for="clientPassword">Password</label>
                    <div class="client-login-input-wrapper">
                        <svg class="client-login-input-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                        </svg>
                        <input type="password" class="client-login-input has-toggle" id="clientPassword" name="password" value="" placeholder="Enter your password" required autocomplete="current-password">
                        
                        <button type="button" class="client-login-toggle-pass" id="toggleClientPasswordBtn" aria-label="Show password">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <div class="client-login-caps-warning" id="clientCapsLockWarning">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Caps Lock is ON
                    </div>
                    <div class="client-login-error-msg"></div>
                </div>

                <!-- Options Row -->
                <div class="client-login-options-row">
                    <label class="client-login-checkbox-label">
                        <input type="checkbox" class="client-login-checkbox" id="rememberClientDevice" checked>
                        Remember this device
                    </label>
                    <a href="#" class="client-login-forgot-link" id="forgotPasswordLink">Forgot password?</a>
                </div>

                <!-- Full-Width Primary Button -->
                <button type="submit" class="btn btn-primary btn-lg client-login-submit-btn" id="clientSubmitBtn">
                    <span class="client-login-btn-text">Sign in to Client Portal</span>
                    <span class="client-login-spinner"></span>
                </button>

                <!-- Outlined Secondary Demo Button -->
                <button type="button" class="btn btn-secondary btn-md client-login-demo-btn" id="useDemoClientBtn">
                    Use Demo Client Access
                </button>

                <!-- Demo Notice Box -->
                <div class="client-login-demo-notice-box" id="clientDemoNoticeBox">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>
                        <strong>Frontend Demo Credentials:</strong> Demo credentials are for frontend preview only.
                    </div>
                </div>

                <!-- Request Access Link Row -->
                <div class="client-login-request-access-row">
                    Don't have portal access?
                    <a href="#" class="client-login-request-link" id="requestAccessLink">Request access</a>
                </div>
            </form>

            <!-- SUCCESS PANEL VIEW (Replaces form on demo login submit, NO redirect to employee dashboard!) -->
            <div class="client-login-success-panel" id="clientSuccessPanel">
                <div class="client-login-success-icon">
                    <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h3 class="client-login-success-title">Client portal preview ready</h3>
                <p class="client-login-success-desc">
                    Welcome to your client workspace preview. In this frontend demonstration, client portal access is simulated locally.
                </p>
                <button type="button" class="btn btn-primary btn-md" id="returnFromSuccessPanelBtn">
                    Return to Login
                </button>
            </div>

            <!-- Card Footer & Account Manager Help Link -->
            <div class="client-login-card-footer">
                Need help accessing your account? <button type="button" class="client-login-mgr-help-btn" id="accountManagerHelpBtn">Contact your account manager</button>
            </div>
        </div>

    </main>
</div>

<!-- FORGOT PASSWORD MODAL (Two-Step View) -->
<div class="client-login-modal-overlay" id="forgotClientPasswordModal" role="dialog" aria-modal="true" aria-labelledby="forgotClientModalTitle">
    <div class="client-login-modal">
        <div class="client-login-modal-header">
            <h3 class="client-login-modal-title" id="forgotClientModalTitle">Reset your password</h3>
            <button type="button" class="client-login-modal-close" id="closeForgotClientModalBtn" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Step 1: Initial Form View -->
        <div id="forgotClientInitialView">
            <form id="forgotClientPasswordForm" novalidate>
                <div class="client-login-modal-body">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                        <div style="width:36px;height:36px;border-radius:var(--radius-md);background-color:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        </div>
                        <p style="font-size:13.5px;color:var(--text-secondary);line-height:1.4;margin:0;">
                            Enter your email address and we'll send password reset instructions.
                        </p>
                    </div>

                    <div class="client-login-form-group" id="forgotClientEmailGroup" style="margin-bottom:0;">
                        <label class="client-login-label" for="forgotClientEmailInput">Email Address</label>
                        <div class="client-login-input-wrapper">
                            <svg class="client-login-input-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                            </svg>
                            <input type="email" class="client-login-input" id="forgotClientEmailInput" placeholder="you@company.com" required>
                        </div>
                        <div class="client-login-error-msg"></div>
                    </div>
                </div>
                <div class="client-login-modal-footer">
                    <button type="button" class="btn btn-secondary btn-md" id="cancelForgotClientModalBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-md" id="submitForgotClientBtn">Send Reset Instructions</button>
                </div>
            </form>
        </div>

        <!-- Step 2: Success Notice View -->
        <div id="forgotClientSuccessView" style="display:none;">
            <div class="client-login-modal-body" style="text-align:center;padding:32px 24px;">
                <div class="client-login-modal-success-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h4 style="font-size:18px;font-weight:700;color:var(--text-heading);margin-bottom:6px;">Check your inbox</h4>
                <p style="font-size:13.5px;color:var(--text-secondary);max-width:360px;margin:0 auto 16px;line-height:1.5;">
                    Frontend demonstration only — no password reset email was sent.
                </p>
            </div>
            <div class="client-login-modal-footer" style="justify-content:center;">
                <button type="button" class="btn btn-primary btn-md" id="returnFromForgotBtn">Return to Login</button>
            </div>
        </div>

    </div>
</div>

<!-- REQUEST ACCESS MODAL (Two-Step View) -->
<div class="client-login-modal-overlay" id="requestAccessModal" role="dialog" aria-modal="true" aria-labelledby="requestAccessModalTitle">
    <div class="client-login-modal">
        <div class="client-login-modal-header">
            <h3 class="client-login-modal-title" id="requestAccessModalTitle">Request Portal Access</h3>
            <button type="button" class="client-login-modal-close" id="closeReqAccessModalBtn" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Step 1: Initial Request Form View -->
        <div id="reqAccessInitialView">
            <form id="requestAccessForm" novalidate>
                <div class="client-login-modal-body">
                    <p style="font-size:13.5px;color:var(--text-secondary);line-height:1.4;margin-bottom:16px;">
                        Submit your details below to request a client portal account.
                    </p>

                    <div class="client-login-form-group" id="reqNameGroup">
                        <label class="client-login-label" for="reqFullName">Full Name *</label>
                        <input type="text" class="client-login-input" id="reqFullName" style="padding-left:14px;" placeholder="Jane Doe" required>
                        <div class="client-login-error-msg"></div>
                    </div>

                    <div class="client-login-form-group" id="reqEmailGroup">
                        <label class="client-login-label" for="reqWorkEmail">Work Email *</label>
                        <input type="email" class="client-login-input" id="reqWorkEmail" style="padding-left:14px;" placeholder="jane@company.com" required>
                        <div class="client-login-error-msg"></div>
                    </div>

                    <div class="client-login-form-group">
                        <label class="client-login-label" for="reqCompanyName">Company Name</label>
                        <input type="text" class="client-login-input" id="reqCompanyName" style="padding-left:14px;" placeholder="Acme Corp">
                    </div>

                    <div class="client-login-form-group" style="margin-bottom:0;">
                        <label class="client-login-label" for="reqMessage">Message (Optional)</label>
                        <textarea class="client-login-input" id="reqMessage" style="padding:8px 14px;height:70px;" placeholder="Specify your account or project reference..."></textarea>
                    </div>
                </div>

                <div class="client-login-modal-footer">
                    <button type="button" class="btn btn-secondary btn-md" id="cancelReqAccessModalBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-md" id="submitReqAccessBtn">Submit Access Request</button>
                </div>
            </form>
        </div>

        <!-- Step 2: Request Access Success View -->
        <div id="reqAccessSuccessView" style="display:none;">
            <div class="client-login-modal-body" style="text-align:center;padding:32px 24px;">
                <div class="client-login-modal-success-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h4 style="font-size:18px;font-weight:700;color:var(--text-heading);margin-bottom:6px;">Request Captured</h4>
                <p style="font-size:13.5px;color:var(--text-secondary);max-width:360px;margin:0 auto 16px;line-height:1.5;">
                    Request captured for frontend preview. No request was sent.
                </p>
            </div>
            <div class="client-login-modal-footer" style="justify-content:center;">
                <button type="button" class="btn btn-primary btn-md" id="returnFromReqAccessBtn">Return to Login</button>
            </div>
        </div>

    </div>
</div>

<!-- ACCOUNT MANAGER HELP POPOVER MODAL -->
<div class="client-login-popover-overlay" id="accountManagerHelpModal" role="dialog" aria-modal="true" aria-labelledby="accountManagerHelpTitle">
    <div class="client-login-modal">
        <div class="client-login-modal-header">
            <h3 class="client-login-modal-title" id="accountManagerHelpTitle">Account Manager Assistance</h3>
            <button type="button" class="client-login-modal-close" id="closeAccountManagerHelpBtn" aria-label="Close modal">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="client-login-modal-body">
            <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:16px;">
                <div style="width:36px;height:36px;border-radius:var(--radius-md);background-color:var(--primary-light);color:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div>
                    <h4 style="font-size:15px;font-weight:600;color:var(--text-heading);margin-bottom:4px;">Contact Your Account Manager</h4>
                    <p style="font-size:13.5px;color:var(--text-secondary);line-height:1.4;margin:0;">
                        Please contact the NexFlow team member who invited you.
                    </p>
                </div>
            </div>

            <div class="client-login-demo-notice-box show" style="margin-top:0;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <div>
                    No support request will be submitted in this demo.
                </div>
            </div>
        </div>
        <div class="client-login-modal-footer">
            <button type="button" class="btn btn-primary btn-md" id="okAccountManagerHelpBtn">Got It</button>
        </div>
    </div>
</div>

<!-- Global Application Scripts -->
<script src="assets/js/client-login.js"></script>

</body>
</html>

