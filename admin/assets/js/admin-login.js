/**
 * NexFlow CRM - Admin Login JavaScript Controller
 * Database-backed administrator authentication controller.
 * Handles form validation, show/hide password toggle, Caps Lock detection,
 * demo login loading state & redirect to index.php, forgot password modal,
 * and toast notifications.
 */

(function () {
    function initAdminLoginPage() {
        const root = document.querySelector(".admin-login-page");
        if (!root) return;

        if (window._adminLoginPageInitialized) return;
        window._adminLoginPageInitialized = true;

        setupFormValidation();
        setupPasswordToggle();
        setupCapsLockDetection();
        setupForgotPasswordModal();
    }

    // Form Validation & Sign In Action
    function setupFormValidation() {
        const form = document.getElementById("adminLoginForm");
        const emailInput = document.getElementById("adminEmail");
        const passInput = document.getElementById("adminPassword");
        const submitBtn = document.getElementById("adminSubmitBtn");

        if (!form || !submitBtn) return;

        // Clear error state on typing
        if (emailInput) {
            emailInput.addEventListener("input", () => clearFieldError("adminEmailGroup"));
        }
        if (passInput) {
            passInput.addEventListener("input", () => clearFieldError("adminPassGroup"));
        }

        form.addEventListener("submit", function (e) {
            e.preventDefault();

            let isValid = true;
            const emailVal = emailInput ? emailInput.value.trim() : "";
            const passVal = passInput ? passInput.value : "";

            // Validate Email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailVal) {
                showFieldError("adminEmailGroup", "Please enter your admin email address.");
                isValid = false;
            } else if (!emailRegex.test(emailVal)) {
                showFieldError("adminEmailGroup", "Please enter a valid email address (e.g. admin@NexFlow.io).");
                isValid = false;
            } else {
                clearFieldError("adminEmailGroup");
            }

            // Validate Password
            if (!passVal) {
                showFieldError("adminPassGroup", "Please enter your password.");
                isValid = false;
            } else if (passVal.length < 4) {
                showFieldError("adminPassGroup", "Password must be at least 4 characters long.");
                isValid = false;
            } else {
                clearFieldError("adminPassGroup");
            }

            if (!isValid) {
                // Focus first failing field
                if (!emailVal || !emailRegex.test(emailVal)) {
                    if (emailInput) emailInput.focus();
                } else if (!passVal) {
                    if (passInput) passInput.focus();
                }
                return;
            }

            submitBtn.classList.add("is-loading");
            submitBtn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('email', emailVal);
            formData.append('password', passVal);

            fetch('api/auth.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(async response => {
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Login failed.');
                }
                showToast('Login successful. Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = result.data.redirect || 'index.php';
                }, 500);
            })
            .catch(error => {
                showFieldError('adminPassGroup', error.message || 'Invalid email or password.');
                submitBtn.classList.remove('is-loading');
                submitBtn.disabled = false;
            });
        });
    }

    // Show / Hide Password Toggle
    function setupPasswordToggle() {
        const toggleBtn = document.getElementById("togglePasswordBtn");
        const passInput = document.getElementById("adminPassword");

        if (!toggleBtn || !passInput) return;

        const eyeIcon = `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
        const eyeOffIcon = `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;

        toggleBtn.addEventListener("click", function () {
            const isPassword = passInput.type === "password";
            passInput.type = isPassword ? "text" : "password";
            this.innerHTML = isPassword ? eyeOffIcon : eyeIcon;
            this.setAttribute("aria-label", isPassword ? "Hide password" : "Show password");
        });
    }

    // Caps Lock Warning Detection
    function setupCapsLockDetection() {
        const passInput = document.getElementById("adminPassword");
        const capsWarning = document.getElementById("capsLockWarning");

        if (!passInput || !capsWarning) return;

        function checkCaps(e) {
            if (e.getModifierState && e.getModifierState("CapsLock")) {
                capsWarning.classList.add("show");
            } else {
                capsWarning.classList.remove("show");
            }
        }

        passInput.addEventListener("keydown", checkCaps);
        passInput.addEventListener("keyup", checkCaps);
        passInput.addEventListener("blur", () => capsWarning.classList.remove("show"));
    }

    // Forgot Password Modal
    function setupForgotPasswordModal() {
        const modal = document.getElementById("forgotPasswordModal");
        const openLink = document.getElementById("forgotPasswordLink");
        const closeBtn = document.getElementById("closeForgotModalBtn");
        const cancelBtn = document.getElementById("cancelForgotModalBtn");
        const form = document.getElementById("forgotPasswordForm");
        const emailInput = document.getElementById("forgotEmailInput");
        const noticeBox = document.getElementById("forgotDemoNotice");

        if (!modal) return;

        function openModal() {
            modal.classList.add("show");
            if (noticeBox) noticeBox.classList.remove("show");
            if (emailInput) {
                emailInput.value = "";
                clearFieldError("forgotEmailGroup");
                setTimeout(() => emailInput.focus(), 50);
            }
        }

        function closeModal() {
            modal.classList.remove("show");
            if (noticeBox) noticeBox.classList.remove("show");
        }

        if (openLink) {
            openLink.addEventListener("click", (e) => {
                e.preventDefault();
                openModal();
            });
        }

        if (closeBtn) closeBtn.addEventListener("click", closeModal);
        if (cancelBtn) cancelBtn.addEventListener("click", closeModal);

        modal.addEventListener("click", (e) => {
            if (e.target === modal) closeModal();
        });

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && modal.classList.contains("show")) {
                closeModal();
            }
        });

        if (form) {
            form.addEventListener("submit", function (e) {
                e.preventDefault();
                const emailVal = emailInput ? emailInput.value.trim() : "";
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (!emailVal || !emailRegex.test(emailVal)) {
                    showFieldError("forgotEmailGroup", "Please enter a valid email address.");
                    if (emailInput) emailInput.focus();
                    return;
                }

                clearFieldError("forgotEmailGroup");
                if (noticeBox) {
                    noticeBox.classList.add("show");
                }

                setTimeout(() => {
                    closeModal();
                }, 2200);
            });
        }
    }

    // Helper functions
    function showFieldError(groupId, msgText) {
        const group = document.getElementById(groupId);
        if (!group) return;
        group.classList.add("has-error");

        const input = group.querySelector("input");
        if (input) input.setAttribute("aria-invalid", "true");

        const msg = group.querySelector(".admin-login-error-msg");
        if (msg) {
            msg.innerHTML = `<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> ${msgText}`;
        }
    }

    function clearFieldError(groupId) {
        const group = document.getElementById(groupId);
        if (!group) return;
        group.classList.remove("has-error");

        const input = group.querySelector("input");
        if (input) input.removeAttribute("aria-invalid");
    }

    // Toast Notification System
    function showToast(message, type = "success") {
        let container = document.querySelector(".admin-login-toast-container");
        if (!container) {
            container = document.createElement("div");
            container.className = "admin-login-toast-container";
            document.body.appendChild(container);
        }

        const iconMap = {
            success: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
            info: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`
        };

        const toast = document.createElement("div");
        toast.className = `admin-login-toast ${type}`;
        toast.innerHTML = `${iconMap[type] || iconMap.success} <span>${message}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = "0";
            toast.style.transition = "opacity 0.25s ease";
            setTimeout(() => toast.remove(), 250);
        }, 3000);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initAdminLoginPage);
    } else {
        initAdminLoginPage();
    }

    window.adminLoginApp = {
        initAdminLoginPage,
        showToast
    };
})();

