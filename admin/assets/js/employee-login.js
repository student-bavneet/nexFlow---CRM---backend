/**
 * NexFlow CRM - Employee Login JavaScript Controller
 * Frontend-only demonstration controller.
 * Handles form validation, "Use Demo Employee Access" button, show/hide password toggle,
 * Caps Lock modifier detection, login loading state & redirect to index.php,
 * two-step Forgot Password modal, administrator help popover, and toast notifications.
 */

(function () {
    let lastFocusedElement = null;

    function initEmployeeLoginPage() {
        const root = document.querySelector(".employee-login-page");
        if (!root) return;

        if (window._employeeLoginPageInitialized) return;
        window._employeeLoginPageInitialized = true;

        setupFormValidation();
        setupDemoAccessButton();
        setupPasswordToggle();
        setupCapsLockDetection();
        setupForgotPasswordModal();
        setupAdminHelpPopover();
    }

    // Form Validation & Sign In Action
    function setupFormValidation() {
        const form = document.getElementById("employeeLoginForm");
        const emailInput = document.getElementById("employeeEmail");
        const passInput = document.getElementById("employeePassword");
        const submitBtn = document.getElementById("employeeSubmitBtn");

        if (!form || !submitBtn) return;

        // Clear error state on input
        if (emailInput) {
            emailInput.addEventListener("input", () => clearFieldError("empEmailGroup"));
        }
        if (passInput) {
            passInput.addEventListener("input", () => clearFieldError("empPassGroup"));
        }

        form.addEventListener("submit", function (e) {
            e.preventDefault();

            let isValid = true;
            const emailVal = emailInput ? emailInput.value.trim() : "";
            const passVal = passInput ? passInput.value : "";

            // Validate Work Email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailVal) {
                showFieldError("empEmailGroup", "Please enter your work email address.");
                isValid = false;
            } else if (!emailRegex.test(emailVal)) {
                showFieldError("empEmailGroup", "Please enter a valid work email address (e.g. name@company.com).");
                isValid = false;
            } else {
                clearFieldError("empEmailGroup");
            }

            // Validate Password
            if (!passVal) {
                showFieldError("empPassGroup", "Please enter your password.");
                isValid = false;
            } else if (passVal.length < 4) {
                showFieldError("empPassGroup", "Password must be at least 4 characters long.");
                isValid = false;
            } else {
                clearFieldError("empPassGroup");
            }

            if (!isValid) {
                if (!emailVal || !emailRegex.test(emailVal)) {
                    if (emailInput) emailInput.focus();
                } else if (!passVal) {
                    if (passInput) passInput.focus();
                }
                return;
            }

            // Frontend Demo Sign In Simulation
            submitBtn.classList.add("is-loading");
            submitBtn.disabled = true;

            const btnText = submitBtn.querySelector(".employee-login-btn-text");
            if (btnText) btnText.textContent = "Signing in...";

            setTimeout(() => {
                showToast("Demo access successful. Opening your workspace.", "success");
                
                setTimeout(() => {
                    window.location.href = "index.php";
                }, 800);
            }, 900);
        });
    }

    // Demo Employee Access Button
    function setupDemoAccessButton() {
        const demoBtn = document.getElementById("useDemoAccessBtn");
        const emailInput = document.getElementById("employeeEmail");
        const passInput = document.getElementById("employeePassword");
        const demoNoticeBox = document.getElementById("employeeDemoNoticeBox");

        if (!demoBtn) return;

        demoBtn.addEventListener("click", function () {
            if (emailInput) {
                emailInput.value = "employee@NexFlow.demo";
                clearFieldError("empEmailGroup");
            }
            if (passInput) {
                passInput.value = "DemoOnly123";
                clearFieldError("empPassGroup");
            }

            if (demoNoticeBox) {
                demoNoticeBox.classList.add("show");
            }

            showToast("Demo employee credentials loaded.", "info");
        });
    }

    // Show / Hide Password Toggle
    function setupPasswordToggle() {
        const toggleBtn = document.getElementById("toggleEmployeePasswordBtn");
        const passInput = document.getElementById("employeePassword");

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
        const passInput = document.getElementById("employeePassword");
        const capsWarning = document.getElementById("employeeCapsLockWarning");

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

    // Forgot Password Modal (Two-Step View)
    function setupForgotPasswordModal() {
        const modal = document.getElementById("forgotEmployeePasswordModal");
        const openLink = document.getElementById("forgotPasswordLink");
        const closeBtn = document.getElementById("closeForgotEmployeeModalBtn");
        const cancelBtn = document.getElementById("cancelForgotEmployeeModalBtn");
        const form = document.getElementById("forgotEmployeePasswordForm");
        const emailInput = document.getElementById("forgotEmployeeEmailInput");
        
        const initialView = document.getElementById("forgotModalInitialView");
        const successView = document.getElementById("forgotModalSuccessView");
        const returnBtn = document.getElementById("returnToLoginBtn");

        if (!modal) return;

        function openModal() {
            lastFocusedElement = document.activeElement;
            modal.classList.add("show");

            // Reset to Step 1 Form View
            if (initialView) initialView.style.display = "block";
            if (successView) successView.style.display = "none";

            if (emailInput) {
                emailInput.value = "";
                clearFieldError("forgotEmpEmailGroup");
                setTimeout(() => emailInput.focus(), 50);
            }
        }

        function closeModal() {
            modal.classList.remove("show");
            if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
                lastFocusedElement.focus();
            }
        }

        if (openLink) {
            openLink.addEventListener("click", (e) => {
                e.preventDefault();
                openModal();
            });
        }

        if (closeBtn) closeBtn.addEventListener("click", closeModal);
        if (cancelBtn) cancelBtn.addEventListener("click", closeModal);
        if (returnBtn) returnBtn.addEventListener("click", closeModal);

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
                    showFieldError("forgotEmpEmailGroup", "Please enter a valid work email address.");
                    if (emailInput) emailInput.focus();
                    return;
                }

                clearFieldError("forgotEmpEmailGroup");

                // Switch to Step 2 Success View
                if (initialView) initialView.style.display = "none";
                if (successView) {
                    successView.style.display = "block";
                    if (returnBtn) setTimeout(() => returnBtn.focus(), 50);
                }
            });
        }
    }

    // Admin Contact Help Popover Modal
    function setupAdminHelpPopover() {
        const popover = document.getElementById("adminHelpPopoverModal");
        const helpBtn = document.getElementById("adminHelpBtn");
        const closeBtn = document.getElementById("closeAdminHelpPopoverBtn");
        const okBtn = document.getElementById("okAdminHelpPopoverBtn");

        if (!popover) return;

        function openPopover() {
            lastFocusedElement = document.activeElement;
            popover.classList.add("show");
            if (okBtn) setTimeout(() => okBtn.focus(), 50);
        }

        function closePopover() {
            popover.classList.remove("show");
            if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
                lastFocusedElement.focus();
            }
        }

        if (helpBtn) {
            helpBtn.addEventListener("click", (e) => {
                e.preventDefault();
                openPopover();
            });
        }

        if (closeBtn) closeBtn.addEventListener("click", closePopover);
        if (okBtn) okBtn.addEventListener("click", closePopover);

        popover.addEventListener("click", (e) => {
            if (e.target === popover) closePopover();
        });

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && popover.classList.contains("show")) {
                closePopover();
            }
        });
    }

    // Field Error Helpers
    function showFieldError(groupId, msgText) {
        const group = document.getElementById(groupId);
        if (!group) return;
        group.classList.add("has-error");

        const input = group.querySelector("input");
        if (input) input.setAttribute("aria-invalid", "true");

        const msg = group.querySelector(".employee-login-error-msg");
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
        let container = document.querySelector(".employee-login-toast-container");
        if (!container) {
            container = document.createElement("div");
            container.className = "employee-login-toast-container";
            document.body.appendChild(container);
        }

        const iconMap = {
            success: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
            info: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`
        };

        const toast = document.createElement("div");
        toast.className = `employee-login-toast ${type}`;
        toast.innerHTML = `${iconMap[type] || iconMap.success} <span>${message}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = "0";
            toast.style.transition = "opacity 0.25s ease";
            setTimeout(() => toast.remove(), 250);
        }, 3000);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initEmployeeLoginPage);
    } else {
        initEmployeeLoginPage();
    }

    window.employeeLoginApp = {
        initEmployeeLoginPage,
        showToast
    };
})();

