<?php
// client-profile.php
$page_title = "My Profile & Settings — NexFlow Client Portal";
$active_nav = "profile";
$page_heading = "Client Profile & Security";

require_once __DIR__ . '/includes/client-auth.php';
require_once __DIR__ . '/includes/client-profile-data.php';

$client = require_client_auth();
$pdo = nexflow_db();

$profile = client_get_full_profile(
    $pdo,
    (int)$client['organization_id'],
    (int)$client['company_id'],
    (int)$client['contact_id'],
    (int)$client['portal_user_id']
);

if (!$profile) {
    $profile = [
        'personal' => [
            'name'         => (string)($client['contact_name'] ?? ''),
            'email'        => (string)($client['contact_email'] ?? ''),
            'phone'        => (string)($client['contact_phone'] ?? ''),
            'job_title'    => (string)($client['job_title'] ?? ''),
            'company_name' => (string)($client['company_name'] ?? ''),
            'timezone'     => 'America/New_York',
        ],
        'company' => [
            'name'         => (string)($client['company_name'] ?? ''),
            'domain'       => '',
            'website'      => '',
            'tax_id'       => '',
            'country'      => 'United States',
            'state'        => '',
            'city'         => '',
            'zip'          => '',
            'language'     => 'English',
            'address'      => '',
            'is_read_only' => true,
        ],
        'notifications' => [
            'email_summaries' => true,
            'in_portal'       => true,
        ],
        'account_lead' => [
            'has_lead' => !empty($client['ae_name']),
            'name'     => (string)($client['ae_name'] ?? ''),
            'title'    => (string)($client['ae_title'] ?? 'Account Executive'),
            'email'    => (string)($client['ae_email'] ?? ''),
        ],
    ];
}

$personal      = $profile['personal'];
$company       = $profile['company'];
$notifications = $profile['notifications'];
$lead          = $profile['account_lead'];
$csrfToken     = client_csrf_token();

include __DIR__ . '/includes/client-header.php';
include __DIR__ . '/includes/client-sidebar.php';
?>

<div class="client-portal-main-wrapper">
    <?php include __DIR__ . '/includes/client-topbar.php'; ?>

    <main class="client-portal-content">
        
        <div style="margin-bottom:20px;">
            <p style="font-size:13.5px;color:#64748B;margin:4px 0 0 0;">Manage your personal profile details, company contact information, and client portal notification settings.</p>
        </div>

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
            
            <!-- Left Column: Personal Info, Company Info & Notification Preferences -->
            <div style="display:flex;flex-direction:column;gap:24px;">
                
                <!-- Personal Contact Information Card -->
                <div class="client-portal-card">
                    <div class="client-portal-card-header">
                        <h3 class="client-portal-card-title">Personal Contact Information</h3>
                    </div>
                    <div class="client-portal-card-body">
                        <form id="clientProfileForm" onsubmit="saveClientProfile(event)">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                                <div>
                                    <label class="client-login-label" for="profName">Full Name *</label>
                                    <input type="text" class="input-control" id="profName" required value="<?php echo htmlspecialchars($personal['name']); ?>" maxlength="150">
                                </div>
                                <div>
                                    <label class="client-login-label" for="profEmail">Work Email *</label>
                                    <input type="email" class="input-control" id="profEmail" required value="<?php echo htmlspecialchars($personal['email']); ?>" maxlength="180">
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                                <div>
                                    <label class="client-login-label" for="profPhone">Phone Number</label>
                                    <input type="text" class="input-control" id="profPhone" value="<?php echo htmlspecialchars($personal['phone']); ?>" maxlength="50">
                                </div>
                                <div>
                                    <label class="client-login-label" for="profRole">Job Title</label>
                                    <input type="text" class="input-control" id="profRole" value="<?php echo htmlspecialchars($personal['job_title']); ?>" maxlength="100">
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                                <div>
                                    <label class="client-login-label" for="profCompany">Company Name</label>
                                    <input type="text" class="input-control" id="profCompany" readonly style="background-color:#F8FAFC;cursor:not-allowed;" value="<?php echo htmlspecialchars($personal['company_name']); ?>">
                                </div>
                                <div>
                                    <label class="client-login-label" for="profTimeZone">Time Zone</label>
                                    <select class="input-control" id="profTimeZone">
                                        <option value="America/New_York" <?php echo ($personal['timezone'] === 'America/New_York') ? 'selected' : ''; ?>>America/New_York (EST)</option>
                                        <option value="America/Chicago" <?php echo ($personal['timezone'] === 'America/Chicago') ? 'selected' : ''; ?>>America/Chicago (CST)</option>
                                        <option value="America/Los_Angeles" <?php echo ($personal['timezone'] === 'America/Los_Angeles') ? 'selected' : ''; ?>>America/Los_Angeles (PST)</option>
                                        <option value="Europe/London" <?php echo ($personal['timezone'] === 'Europe/London') ? 'selected' : ''; ?>>Europe/London (GMT)</option>
                                        <option value="Asia/Kolkata" <?php echo ($personal['timezone'] === 'Asia/Kolkata') ? 'selected' : ''; ?>>Asia/Kolkata (IST)</option>
                                        <option value="UTC" <?php echo ($personal['timezone'] === 'UTC') ? 'selected' : ''; ?>>UTC</option>
                                    </select>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <button type="submit" id="btnSaveProfile" class="btn btn-primary btn-sm">Save Profile Changes</button>
                                <span id="profileStatusMsg" style="font-size:12.5px;color:#16A34A;display:none;"></span>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Dedicated Company Information Card (Read-Only) -->
                <div class="client-portal-card">
                    <div class="client-portal-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                        <h3 class="client-portal-card-title">Company Information</h3>
                        <span style="font-size:11px;font-weight:600;color:#64748B;background-color:#F1F5F9;padding:2px 8px;border-radius:4px;">READ ONLY</span>
                    </div>
                    <div class="client-portal-card-body">
                        <div style="margin-bottom:16px;background-color:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:10px 14px;font-size:12.5px;color:#64748B;display:flex;align-items:flex-start;gap:8px;">
                            <svg width="16" height="16" fill="none" stroke="#64748B" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:2px;">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                            </svg>
                            <span>Company entity, legal name, and tax registration records are managed by your Account Executive. Please use the <strong>Account Lead Support</strong> action to request legal modifications.</span>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div>
                                <label class="client-login-label">Company Name</label>
                                <input type="text" class="input-control" id="compName" readonly style="background-color:#F8FAFC;cursor:not-allowed;" value="<?php echo htmlspecialchars($company['name']); ?>">
                            </div>
                            <div>
                                <label class="client-login-label">Company Code</label>
                                <input type="text" class="input-control" id="compCode" readonly style="background-color:#F8FAFC;cursor:not-allowed;" value="<?php echo htmlspecialchars($company['company_code'] ?? ''); ?>">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div>
                                <label class="client-login-label">Industry</label>
                                <input type="text" class="input-control" id="compIndustry" readonly style="background-color:#F8FAFC;cursor:not-allowed;" value="<?php echo htmlspecialchars($company['industry'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="client-login-label">Website</label>
                                <input type="text" class="input-control" id="compWebsite" readonly style="background-color:#F8FAFC;cursor:not-allowed;" value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div>
                                <label class="client-login-label">VAT / Tax Number</label>
                                <input type="text" class="input-control" id="compTaxId" readonly style="background-color:#F8FAFC;cursor:not-allowed;" value="<?php echo htmlspecialchars($company['tax_id'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="client-login-label">Country / Region</label>
                                <input type="text" class="input-control" id="compCountry" readonly style="background-color:#F8FAFC;cursor:not-allowed;" value="<?php echo htmlspecialchars($company['country'] ?? ''); ?>">
                            </div>
                        </div>
                        <div style="margin-bottom:16px;">
                            <label class="client-login-label">Location / Address</label>
                            <textarea class="input-control" id="compAddress" rows="2" readonly style="height:60px;resize:none;padding:8px 12px;background-color:#F8FAFC;cursor:not-allowed;"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Notification Settings Card -->
                <div class="client-portal-card">
                    <div class="client-portal-card-header">
                        <h3 class="client-portal-card-title">Notification Preferences</h3>
                    </div>
                    <div class="client-portal-card-body">
                        <div style="display:flex;flex-direction:column;gap:14px;">
                            <label style="display:flex;align-items:center;gap:10px;font-size:13.5px;color:#0F172A;cursor:pointer;">
                                <input type="checkbox" id="notifEmailCheck" class="client-portal-task-checkbox" <?php echo !empty($notifications['email_summaries']) ? 'checked' : ''; ?> onchange="saveNotificationPref()">
                                <div>
                                    <strong>Email Summaries &amp; Updates</strong>
                                    <div style="font-size:12px;color:#64748B;">Receive email updates when proposals or SLA documents are shared.</div>
                                </div>
                            </label>

                            <label style="display:flex;align-items:center;gap:10px;font-size:13.5px;color:#0F172A;cursor:pointer;">
                                <input type="checkbox" id="notifInAppCheck" class="client-portal-task-checkbox" <?php echo !empty($notifications['in_portal']) ? 'checked' : ''; ?> onchange="saveNotificationPref()">
                                <div>
                                    <strong>In-Portal Notifications</strong>
                                    <div style="font-size:12px;color:#64748B;">Display banner badges and topbar notifications for new messages.</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Portal Password & Workspace Security Support -->
            <div style="display:flex;flex-direction:column;gap:24px;">
                
                <!-- Password Management Card -->
                <div class="client-portal-card">
                    <div class="client-portal-card-header">
                        <h3 class="client-portal-card-title">Portal Password</h3>
                    </div>
                    <div class="client-portal-card-body">
                        <form id="changePassForm" onsubmit="handleChangePassword(event)">
                            <div style="margin-bottom:12px;">
                                <label class="client-login-label" for="currentPassInput">Current Password</label>
                                <input type="password" class="input-control" id="currentPassInput" placeholder="••••••••" required autocomplete="current-password">
                            </div>
                            <div style="margin-bottom:12px;">
                                <label class="client-login-label" for="newPassInput">New Password</label>
                                <input type="password" class="input-control" id="newPassInput" placeholder="Min. 6 characters" required autocomplete="new-password">
                            </div>
                            <div style="margin-bottom:16px;">
                                <label class="client-login-label" for="confirmPassInput">Confirm New Password</label>
                                <input type="password" class="input-control" id="confirmPassInput" placeholder="Repeat new password" required autocomplete="new-password">
                            </div>
                            <button type="submit" id="btnUpdatePass" class="btn btn-secondary btn-sm" style="width:100%;">Update Password</button>
                        </form>
                        <div style="margin-top:12px;font-size:11.5px;color:#64748B;">
                            Password must be at least 6 characters. Changing your password updates your active portal credentials.
                        </div>
                    </div>
                </div>

                <!-- Account Manager Contact Card -->
                <div class="client-portal-card">
                    <div class="client-portal-card-header">
                        <h3 class="client-portal-card-title">Account Lead Support</h3>
                    </div>
                    <div class="client-portal-card-body" style="font-size:13px;">
                        <?php if (!empty($lead['has_lead'])): ?>
                            <p style="color:#475569;margin-top:0;">Your assigned Account Executive is <strong><?php echo htmlspecialchars($lead['name']); ?></strong> (<?php echo htmlspecialchars($lead['title']); ?>). Need assistance changing your organization's legal entity or primary workspace owner?</p>
                            <button type="button" class="btn btn-secondary btn-xs" style="margin-top: 10px;" onclick="window.clientPortal.openQuickMessageModal('Account Admin Question')">
                                Contact <?php echo htmlspecialchars($lead['name']); ?>
                            </button>
                        <?php else: ?>
                            <p style="color:#475569;margin-top:0;">Need assistance changing your organization's legal entity or primary workspace owner? No dedicated Account Executive is currently assigned. Please submit your inquiry through the Client Portal Messages inbox.</p>
                            <a href="client-messages.php" class="btn btn-secondary btn-xs" style="margin-top: 10px;display:inline-block;">
                                Open Messages Inbox
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>

    </main>
</div>

<script>
window.INITIAL_PROFILE = <?php echo json_encode($profile, JSON_UNESCAPED_UNICODE); ?>;
window.clientPortalCsrf = <?php echo json_encode($csrfToken, JSON_UNESCAPED_UNICODE); ?>;

function saveClientProfile(e) {
    e.preventDefault();
    const btn = document.getElementById("btnSaveProfile");
    const statusMsg = document.getElementById("profileStatusMsg");
    if (!btn) return;

    const fullName = document.getElementById("profName").value.trim();
    const email = document.getElementById("profEmail").value.trim();
    const phone = document.getElementById("profPhone").value.trim();
    const jobTitle = document.getElementById("profRole").value.trim();
    const timezone = document.getElementById("profTimeZone").value;

    if (!fullName) {
        window.clientPortal.showToast("Full Name is required.", "warning");
        return;
    }
    if (!email) {
        window.clientPortal.showToast("Work Email is required.", "warning");
        return;
    }

    btn.disabled = true;
    btn.textContent = "Saving...";
    if (statusMsg) statusMsg.style.display = "none";

    const formData = new FormData();
    formData.append("action", "update_profile");
    formData.append("csrf_token", window.clientPortalCsrf);
    formData.append("name", fullName);
    formData.append("email", email);
    formData.append("phone", phone);
    formData.append("job_title", jobTitle);
    formData.append("timezone", timezone);

    fetch("api/client-profile.php", {
        method: "POST",
        body: formData,
        credentials: "same-origin"
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.textContent = "Save Profile Changes";

        if (!res.success) {
            window.clientPortal.showToast(res.message || "Failed to update profile.", "warning");
            return;
        }

        // Update DOM elements in sidebar and topbar
        const sidebarName = document.getElementById("sidebarUserName");
        const topbarName = document.getElementById("topbarUserName");
        const menuName = document.getElementById("menuUserName");
        const menuEmail = document.getElementById("menuUserEmail");
        const sidebarRole = document.getElementById("sidebarUserRole");
        const sidebarAvatar = document.getElementById("sidebarUserAvatar");
        const topbarAvatar = document.getElementById("topbarUserAvatar");

        if (sidebarName) sidebarName.textContent = fullName;
        if (topbarName) topbarName.textContent = fullName;
        if (menuName) menuName.textContent = fullName;
        if (menuEmail) menuEmail.textContent = email;
        if (sidebarRole && jobTitle) sidebarRole.textContent = jobTitle;

        if (res.data && res.data.personal && res.data.personal.avatar_initials) {
            if (sidebarAvatar) sidebarAvatar.textContent = res.data.personal.avatar_initials;
            if (topbarAvatar) topbarAvatar.textContent = res.data.personal.avatar_initials;
        }

        // Update in-memory user object
        if (window.clientPortal && window.clientPortal.data && window.clientPortal.data.user) {
            window.clientPortal.data.user.name = fullName;
            window.clientPortal.data.user.email = email;
            window.clientPortal.data.user.phone = phone;
            window.clientPortal.data.user.role = jobTitle;
            window.clientPortal.data.user.timeZone = timezone;
        }

        window.clientPortal.showToast(res.message || "Profile updated successfully.", "success");
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = "Save Profile Changes";
        console.error("Profile update error:", err);
        window.clientPortal.showToast("Network error while updating profile.", "danger");
    });
}

function saveNotificationPref() {
    const emailSummaries = document.getElementById("notifEmailCheck").checked;
    const inPortal = document.getElementById("notifInAppCheck").checked;

    const formData = new FormData();
    formData.append("action", "update_notifications");
    formData.append("csrf_token", window.clientPortalCsrf);
    formData.append("email_summaries", emailSummaries ? "1" : "0");
    formData.append("in_portal", inPortal ? "1" : "0");

    fetch("api/client-profile.php", {
        method: "POST",
        body: formData,
        credentials: "same-origin"
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            window.clientPortal.showToast(res.message || "Failed to update preferences.", "warning");
            return;
        }
        if (window.clientPortal && window.clientPortal.data && window.clientPortal.data.user) {
            window.clientPortal.data.user.notificationEmail = emailSummaries;
            window.clientPortal.data.user.notificationInApp = inPortal;
        }
        window.clientPortal.showToast("Notification preferences saved.", "info");
    })
    .catch(err => {
        console.error("Notification update error:", err);
        window.clientPortal.showToast("Network error while updating preferences.", "danger");
    });
}

function handleChangePassword(e) {
    e.preventDefault();
    const btn = document.getElementById("btnUpdatePass");
    const currentPass = document.getElementById("currentPassInput").value;
    const newPass = document.getElementById("newPassInput").value;
    const confirmPass = document.getElementById("confirmPassInput").value;

    if (!currentPass || !newPass || !confirmPass) {
        window.clientPortal.showToast("All password fields are required.", "warning");
        return;
    }

    if (newPass.length < 6) {
        window.clientPortal.showToast("New password must be at least 6 characters.", "warning");
        return;
    }

    if (newPass !== confirmPass) {
        window.clientPortal.showToast("New passwords do not match.", "warning");
        return;
    }

    btn.disabled = true;
    btn.textContent = "Updating...";

    const formData = new FormData();
    formData.append("action", "change_password");
    formData.append("csrf_token", window.clientPortalCsrf);
    formData.append("current_password", currentPass);
    formData.append("new_password", newPass);
    formData.append("confirm_password", confirmPass);

    fetch("api/client-profile.php", {
        method: "POST",
        body: formData,
        credentials: "same-origin"
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.textContent = "Update Password";

        if (!res.success) {
            window.clientPortal.showToast(res.message || "Failed to update password.", "warning");
            return;
        }

        document.getElementById("changePassForm").reset();
        window.clientPortal.showToast(res.message || "Password updated successfully.", "success");
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = "Update Password";
        console.error("Password change error:", err);
        window.clientPortal.showToast("Network error while updating password.", "danger");
    });
}
</script>

<?php include __DIR__ . '/includes/client-footer.php'; ?>
