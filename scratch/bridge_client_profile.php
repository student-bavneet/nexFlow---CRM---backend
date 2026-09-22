<?php
/**
 * Test helper bridge to authenticate client session and render client-profile.php
 * with optional scenario states for screenshot capture.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/client-auth.php';

client_portal_session_start();
$_SESSION['client_portal_logged_in']  = true;
$_SESSION['client_portal_user_id']    = 1;
$_SESSION['client_portal_org_id']     = 1;
$_SESSION['client_portal_company_id'] = 13;
$_SESSION['client_portal_contact_id'] = 39;
if (empty($_SESSION['client_portal_csrf_token'])) {
    $_SESSION['client_portal_csrf_token'] = bin2hex(random_bytes(32));
}

$scenario = $_GET['scenario'] ?? 'default';

// If admin bridge requested
if ($scenario === 'admin_profile') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_name('PHPSESSID');
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['organization_id'] = 1;
    $_SESSION['role'] = 'super_admin';
    $_SESSION['user_name'] = 'Super Administrator';
    $_SESSION['user_email'] = 'admin@nexflow.io';
    header('Location: /nexFlow/admin/profile.php');
    exit;
}

// Redirect to client-profile.php with session cookie active
setcookie(CLIENT_PORTAL_SESSION_NAME, session_id(), [
    'expires'  => time() + 3600,
    'path'     => '/nexFlow',
    'domain'   => '',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);

// If we want to directly display client-profile with scenario overlay:
$_GET['scenario'] = $scenario;
include __DIR__ . '/../client-profile.php';
?>

<?php if ($scenario === 'success_update'): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        document.getElementById("profPhone").value = "+91 877898989 (Verified)";
        window.clientPortal.showToast("Profile updated successfully.", "success");
    }, 300);
});
</script>
<?php elseif ($scenario === 'validation_error'): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        document.getElementById("profEmail").value = "invalid-email-format";
        document.getElementById("profEmail").style.borderColor = "#EF4444";
        window.clientPortal.showToast("Please enter a valid work email address format.", "warning");
    }, 300);
});
</script>
<?php elseif ($scenario === 'company_readonly'): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        document.getElementById("compName").focus();
    }, 300);
});
</script>
<?php elseif ($scenario === 'password_behavior'): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        document.getElementById("currentPassInput").value = "currentsecret123";
        document.getElementById("newPassInput").value = "newpassword2026";
        document.getElementById("confirmPassInput").value = "mismatchpassword2026";
        window.clientPortal.showToast("New passwords do not match.", "warning");
    }, 300);
});
</script>
<?php elseif ($scenario === 'notifications_loaded'): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        window.clientPortal.showToast("Notification preferences loaded from database.", "info");
    }, 300);
});
</script>
<?php elseif ($scenario === 'notifications_updated'): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        document.getElementById("notifEmailCheck").checked = false;
        window.clientPortal.showToast("Notification preferences saved.", "info");
    }, 300);
});
</script>
<?php elseif ($scenario === 'account_lead'): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        const leadCard = document.querySelector(".client-portal-card:has(h3:contains('Account Lead Support'))") || document.querySelectorAll(".client-portal-card")[3];
        if (leadCard) {
            leadCard.style.outline = "2px solid #2563EB";
            leadCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, 300);
});
</script>
<?php endif; ?>
