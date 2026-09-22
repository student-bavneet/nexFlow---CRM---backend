/**
 * NexFlow CRM - Client Login JavaScript Controller
 * Frontend-only demonstration controller.
 * Handles form validation, "Use Demo Client Access" button, show/hide password toggle,
 * Caps Lock modifier detection, client success panel view (NO redirect to CRM dashboard),
 * two-step Forgot Password modal, Request Access modal, Account Manager help popover,
 * and toast notifications.
 */

(function () {
    let lastFocusedElement = null;

    function initClientLoginPage() {
        const root = document.querySelector(".client-login-page");
        if (!root) return;

        if (window._clientLoginPageInitialized) return;
        window._clientLoginPageInitialized = true;

        setupFormValidation();
        setupDemoAccessButton();
        setupPasswordToggle();
        setupCapsLockDetection();
        setupForgotPasswordModal();
        setupRequestAccessModal();
        setupAccountManagerHelpPopover();
    }

    // Form Validation & Client Portal Demo Sign In Action
    function setupFormValidation() {
        const form = document.getElementById("clientLoginForm");
        const successPanel = document.getElementById("clientSuccessPanel");
        const returnBtn = document.getElementById("returnFromSuccessPanelBtn");
        const emailInput = document.getElementById("clientEmail");
        const passInput = document.getElementById("clientPassword");
        const submitBtn = document.getElementById("clientSubmitBtn");

        if (!form || !submitBtn) return;

        // Clear error state on input
        if (emailInput) {
            emailInput.addEventListener("input", () => clearFieldError("clientEmailGroup"));
        }
        if (passInput) {
            passInput.addEventListener("input", () => clearFieldError("clientPassGroup"));
        }

        form.addEventListener("submit", function (e) {
            e.preventDefault();

            let isValid = true;
            const emailVal = emailInput ? emailInput.value.trim() : "";
            const passVal = passInput ? passInput.value : "";

            // Validate Email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailVal) {
                showFieldError("clientEmailGroup", "Please enter your email address.");
                isValid = false;
            } else if (!emailRegex.test(emailVal)) {
                showFieldError("clientEmailGroup", "Please enter a valid email address (e.g. you@company.com).");
                isValid = false;
            } else {
                clearFieldError("clientEmailGroup");
            }

            // Validate Password
            if (!passVal) {
                showFieldError("clientPassGroup", "Please enter your password.");
                isValid = false;
            } else if (passVal.length < 4) {
                showFieldError("clientPassGroup", "Password must be at least 4 characters long.");
                isValid = false;
            } else {
                clearFieldError("clientPassGroup");
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

            const btnText = submitBtn.querySelector(".client-login-btn-text");
            if (btnText) btnText.textContent = "Signing in...";

            setTimeout(() => {
                submitBtn.classList.remove("is-loading");
                submitBtn.disabled = false;
                if (btnText) btnText.textContent = "Sign in to Client Portal";

                showToast("Demo client login successful.", "success");

                // Replace form temporarily with Client Portal Preview Success Panel
                if (form) form.style.display = "none";
                if (successPanel) successPanel.classList.add("show");
            }, 900);
        });

        if (returnBtn) {
            returnBtn.addEventListener("click", function () {
                if (successPanel) successPanel.classList.remove("show");
                if (form) form.style.display = "block";
            });
        }
    }

    // Demo Client Access Button
    function setupDemoAccessButton() {
        const demoBtn = document.getElementById("useDemoClientBtn");
        const emailInput = document.getElementById("clientEmail");
        const passInput = document.getElementById("clientPassword");
        const demoNoticeBox = document.getElementById("clientDemoNoticeBox");

        if (!demoBtn) return;

        demoBtn.addEventListener("click", function () {
            if (emailInput) {
                emailInput.value = "client@NexFlow.demo";
                clearFieldError("clientEmailGroup");
            }
            if (passInput) {
                passInput.value = "DemoClient123";
                clearFieldError("clientPassGroup");
            }

            if (demoNoticeBox) {
                demoNoticeBox.classList.add("show");
            }

            showToast("Demo client credentials loaded.", "info");
        });
    }

    // Show / Hide Password Toggle
    function setupPasswordToggle() {
        const toggleBtn = document.getElementById("toggleClientPasswordBtn");
        const passInput = document.getElementById("clientPassword");

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
        const passInput = document.getElementById("clientPassword");
        const capsWarning = document.getElementById("clientCapsLockWarning");

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
        const modal = document.getElementById("forgotClientPasswordModal");
        const openLink = document.getElementById("forgotPasswordLink");
        const closeBtn = document.getElementById("closeForgotClientModalBtn");
        const cancelBtn = document.getElementById("cancelForgotClientModalBtn");
        const form = document.getElementById("forgotClientPasswordForm");
        const emailInput = document.getElementById("forgotClientEmailInput");
        
        const initialView = document.getElementById("forgotClientInitialView");
        const successView = document.getElementById("forgotClientSuccessView");
        const returnBtn = document.getElementById("returnFromForgotBtn");

        if (!modal) return;

        function openModal() {
            lastFocusedElement = document.activeElement;
            modal.classList.add("show");

            // Reset to Step 1 Form View
            if (initialView) initialView.style.display = "block";
            if (successView) successView.style.display = "none";

            if (emailInput) {
                emailInput.value = "";
                clearFieldError("forgotClientEmailGroup");
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
                    showFieldError("forgotClientEmailGroup", "Please enter a valid email address.");
                    if (emailInput) emailInput.focus();
                    return;
                }

                clearFieldError("forgotClientEmailGroup");

                // Switch to Step 2 Success View
                if (initialView) initialView.style.display = "none";
                if (successView) {
                    successView.style.display = "block";
                    if (returnBtn) setTimeout(() => returnBtn.focus(), 50);
                }
            });
        }
    }

    // Request Access Modal (Two-Step View)
    function setupRequestAccessModal() {
        const modal = document.getElementById("requestAccessModal");
        const openLink = document.getElementById("requestAccessLink");
        const closeBtn = document.getElementById("closeReqAccessModalBtn");
        const cancelBtn = document.getElementById("cancelReqAccessModalBtn");
        const form = document.getElementById("requestAccessForm");
        const nameInput = document.getElementById("reqFullName");
        const emailInput = document.getElementById("reqWorkEmail");
        
        const initialView = document.getElementById("reqAccessInitialView");
        const successView = document.getElementById("reqAccessSuccessView");
        const returnBtn = document.getElementById("returnFromReqAccessBtn");

        if (!modal) return;

        function openModal() {
            lastFocusedElement = document.activeElement;
            modal.classList.add("show");

            if (initialView) initialView.style.display = "block";
            if (successView) successView.style.display = "none";

            if (nameInput) {
                nameInput.value = "";
                clearFieldError("reqNameGroup");
            }
            if (emailInput) {
                emailInput.value = "";
                clearFieldError("reqEmailGroup");
            }
            setTimeout(() => { if (nameInput) nameInput.focus(); }, 50);
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
                let isValid = true;

                const nameVal = nameInput ? nameInput.value.trim() : "";
                const emailVal = emailInput ? emailInput.value.trim() : "";
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (!nameVal) {
                    showFieldError("reqNameGroup", "Please enter your full name.");
                    isValid = false;
                } else {
                    clearFieldError("reqNameGroup");
                }

                if (!emailVal || !emailRegex.test(emailVal)) {
                    showFieldError("reqEmailGroup", "Please enter a valid work email.");
                    isValid = false;
                } else {
                    clearFieldError("reqEmailGroup");
                }

                if (!isValid) return;

                // Switch to Step 2 Success View
                if (initialView) initialView.style.display = "none";
                if (successView) {
                    successView.style.display = "block";
                    if (returnBtn) setTimeout(() => returnBtn.focus(), 50);
                }
            });
        }
    }

    // Account Manager Help Popover Modal
    function setupAccountManagerHelpPopover() {
        const popover = document.getElementById("accountManagerHelpModal");
        const helpBtn = document.getElementById("accountManagerHelpBtn");
        const closeBtn = document.getElementById("closeAccountManagerHelpBtn");
        const okBtn = document.getElementById("okAccountManagerHelpBtn");

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

        const msg = group.querySelector(".client-login-error-msg");
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
        let container = document.querySelector(".client-login-toast-container");
        if (!container) {
            container = document.createElement("div");
            container.className = "client-login-toast-container";
            document.body.appendChild(container);
        }

        const iconMap = {
            success: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
            info: `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`
        };

        const toast = document.createElement("div");
        toast.className = `client-login-toast ${type}`;
        toast.innerHTML = `${iconMap[type] || iconMap.success} <span>${message}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = "0";
            toast.style.transition = "opacity 0.25s ease";
            setTimeout(() => toast.remove(), 250);
        }, 3000);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initClientLoginPage);
    } else {
        initClientLoginPage();
    }

    window.clientLoginApp = {
        initClientLoginPage,
        showToast
    };
})();

