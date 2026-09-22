/**
 * NexFlow CRM Lead Communication Actions JavaScript
 * Handles Call Choice, WhatsApp Compose, Email Compose, templates, live previews,
 * tokens, validation, cursor insertion, single-layer management, and opening tel:, wa.me, and mailto: links.
 */

(function() {
    let activeLeadForComm = null;
    let lastCommTriggerElement = null;

    document.addEventListener("DOMContentLoaded", function() {
        initCommunicationEvents();
    });

    window.leadComm = {
        closeAllLayers: function() {
            closeAllCommunicationLayers();
        },

        openCallChoice: function(leadId, triggerElement) {
            closeAllCommunicationLayers();

            const lead = findLead(leadId);
            if (!lead) return;

            activeLeadForComm = lead;
            lastCommTriggerElement = triggerElement || document.activeElement;

            const avatar = document.getElementById("callChoiceAvatar");
            const name = document.getElementById("callChoiceName");
            const sub = document.getElementById("callChoiceSub");
            const notice = document.getElementById("callChoiceNotice");
            const buttons = document.getElementById("callChoiceButtons");

            if (avatar) {
                avatar.textContent = lead.name.split(" ").map(n => n[0]).join("").toUpperCase();
                avatar.style.backgroundColor = lead.assigneeColor || "#7C3AED";
            }
            if (name) name.textContent = lead.name;
            if (sub) sub.textContent = (lead.company || "Lead") + " • " + (lead.phone || "No phone");

            const hasValidPhone = !!(lead.phone && (lead.cleanPhone || lead.phone.replace(/[^0-9]/g, '')));

            if (!hasValidPhone) {
                if (notice) notice.style.display = "block";
                if (buttons) buttons.style.display = "none";
            } else {
                if (notice) notice.style.display = "none";
                if (buttons) buttons.style.display = "flex";
            }

            openModal("callChoiceModal");
        },

        openWhatsApp: function(leadId, triggerElement) {
            closeAllCommunicationLayers();

            const lead = findLead(leadId);
            if (!lead) return;

            activeLeadForComm = lead;
            lastCommTriggerElement = triggerElement || document.activeElement;

            const avatar = document.getElementById("waAvatar");
            const recipName = document.getElementById("waRecipientName");
            const recipCompany = document.getElementById("waRecipientCompany");
            const recipPhone = document.getElementById("waRecipientPhone");
            const notice = document.getElementById("whatsappNotice");
            const btnContinue = document.getElementById("btnContinueWA");

            if (avatar) {
                avatar.textContent = lead.name.split(" ").map(n => n[0]).join("").toUpperCase();
                avatar.style.backgroundColor = lead.assigneeColor || "#7C3AED";
            }
            if (recipName) recipName.textContent = lead.name;
            if (recipCompany) recipCompany.textContent = lead.company || "Company";
            if (recipPhone) recipPhone.textContent = lead.phone || "No phone available";

            const cleanNum = getCleanPhone(lead);
            const hasValidWA = !!cleanNum && cleanNum.length >= 7;

            if (!hasValidWA) {
                if (notice) {
                    notice.textContent = "No valid WhatsApp number is available for this lead.";
                    notice.style.display = "block";
                }
                if (btnContinue) btnContinue.disabled = true;
            } else {
                if (notice) notice.style.display = "none";
                if (btnContinue) btnContinue.disabled = false;
            }

            // Reset template chips
            document.querySelectorAll("#whatsappModal .template-chip").forEach((chip, idx) => {
                chip.classList.toggle("active", idx === 0);
            });

            applyWATemplate("intro");
            openModal("whatsappModal");
        },

        openEmail: function(leadId, triggerElement) {
            closeAllCommunicationLayers();

            const lead = findLead(leadId);
            if (!lead) return;

            activeLeadForComm = lead;
            lastCommTriggerElement = triggerElement || document.activeElement;

            const chipAvatar = document.getElementById("emailChipAvatar");
            const chipName = document.getElementById("emailChipName");
            const chipEmail = document.getElementById("emailChipEmail");
            const notice = document.getElementById("emailNotice");
            const btnOpenApp = document.getElementById("btnOpenEmailApp");

            if (chipAvatar) {
                chipAvatar.textContent = lead.name.split(" ").map(n => n[0]).join("").toUpperCase();
                chipAvatar.style.backgroundColor = lead.assigneeColor || "#7C3AED";
            }
            if (chipName) chipName.textContent = lead.name;
            if (chipEmail) chipEmail.textContent = `<${lead.email || "No email"}>`;

            const isValidEmail = validateEmailFormat(lead.email);

            if (!isValidEmail) {
                if (notice) {
                    notice.textContent = "No email address is available for this lead.";
                    notice.style.display = "block";
                }
                if (btnOpenApp) btnOpenApp.disabled = true;
            } else {
                if (notice) notice.style.display = "none";
                if (btnOpenApp) btnOpenApp.disabled = false;
            }

            // Populate Context Sidebar
            populateEmailContextSidebar(lead);

            // Reset template chips
            document.querySelectorAll("#emailModal .template-chip").forEach((chip, idx) => {
                chip.classList.toggle("active", idx === 0);
            });

            applyEmailTemplate("intro");
            openModal("emailModal");
        }
    };

    function closeAllCommunicationLayers() {
        // 1. Close Call Modal
        const callModal = document.getElementById("callChoiceModal");
        if (callModal) {
            callModal.classList.remove("show", "is-open");
            callModal.setAttribute("aria-hidden", "true");
        }

        // 2. Close WhatsApp Drawer
        const waModal = document.getElementById("whatsappModal");
        if (waModal) {
            waModal.classList.remove("show", "is-open");
            waModal.setAttribute("aria-hidden", "true");
        }

        // 3. Close Email Modal
        const emailModal = document.getElementById("emailModal");
        if (emailModal) {
            emailModal.classList.remove("show", "is-open");
            emailModal.setAttribute("aria-hidden", "true");
        }

        // 4. Close Lead Details Drawer if open
        if (typeof window.closeLeadDrawer === "function") {
            const drawer = document.getElementById("leadDrawer");
            if (drawer && drawer.classList.contains("show")) {
                window.closeLeadDrawer();
            }
        }

        // 5. Close any open row menus
        document.querySelectorAll(".row-menu.show").forEach(m => m.classList.remove("show"));

        document.body.style.overflow = "";
    }

    function populateEmailContextSidebar(lead) {
        const ctxAvatar = document.getElementById("ctxAvatar");
        const ctxName = document.getElementById("ctxName");
        const ctxCompany = document.getElementById("ctxCompany");
        const ctxStatus = document.getElementById("ctxStatus");
        const ctxValue = document.getElementById("ctxValue");
        const ctxActivity = document.getElementById("ctxActivity");

        if (ctxAvatar) {
            ctxAvatar.textContent = lead.name.split(" ").map(n => n[0]).join("").toUpperCase();
            ctxAvatar.style.backgroundColor = lead.assigneeColor || "#7C3AED";
        }
        if (ctxName) ctxName.textContent = lead.name;
        if (ctxCompany) ctxCompany.textContent = lead.company;
        if (ctxStatus) ctxStatus.textContent = lead.status || "Qualified";
        if (ctxValue) ctxValue.textContent = lead.value ? `$${lead.value.toLocaleString()}` : "$24,000";
        if (ctxActivity) ctxActivity.textContent = lead.lastActivity || "Recently";
    }

    function findLead(leadId) {
        // Try LEADS_MOCK_DATA
        if (window.LEADS_MOCK_DATA && Array.isArray(window.LEADS_MOCK_DATA)) {
            const found = window.LEADS_MOCK_DATA.find(l => l.id === leadId);
            if (found) {
                return {
                    ...found,
                    cleanPhone: found.cleanPhone || (found.phone ? found.phone.replace(/[^0-9]/g, '') : '14155550123')
                };
            }
        }
        // Try CONTACTS_MOCK_DATA
        if (window.CONTACTS_MOCK_DATA && Array.isArray(window.CONTACTS_MOCK_DATA)) {
            const found = window.CONTACTS_MOCK_DATA.find(c => c.id === leadId);
            if (found) {
                return {
                    id: found.id,
                    name: found.name,
                    company: found.company,
                    phone: found.phone || "+1 (415) 555-0123",
                    cleanPhone: found.cleanPhone || (found.phone ? found.phone.replace(/[^0-9]/g, '') : '14155550123'),
                    email: found.email || `${found.name.toLowerCase().replace(/\s+/g, '')}@example.com`,
                    assignee: found.owner || "Sarah Chen",
                    assigneeInitials: found.ownerInitials || "SC",
                    assigneeColor: found.ownerColor || "#7C3AED",
                    status: found.relationship || "Qualified",
                    value: found.openDealsValue || 24000,
                    lastActivity: found.lastInteraction || "Recently"
                };
            }
        }
        // Try DOM Row lookup
        const tr = document.querySelector(`tr[data-id="${leadId}"]`);
        if (tr) {
            const name = tr.getAttribute("data-name") || tr.querySelector(".lead-name-clickable")?.textContent?.trim() || "Lead";
            const company = tr.getAttribute("data-company") || "Company";
            const status = tr.getAttribute("data-status") || "Qualified";
            const emailEl = tr.querySelector("td:nth-child(2) div div:last-child");
            const email = emailEl ? emailEl.textContent.trim() : `${name.toLowerCase().replace(/\s+/g, '')}@example.com`;
            const valEl = tr.querySelector("td:nth-child(6)");
            const valStr = valEl ? valEl.textContent.replace(/[^0-9]/g, '') : "24000";
            const value = parseInt(valStr) || 24000;
            const ownerEl = tr.querySelector("td:nth-child(8) span");
            const assignee = ownerEl ? ownerEl.textContent.trim() : "Sarah Chen";
            const actEl = tr.querySelector("td:nth-child(9)");
            const lastActivity = actEl ? actEl.textContent.trim() : "Recently";

            return {
                id: leadId,
                name: name,
                company: company,
                phone: "+1 (415) 555-0123",
                cleanPhone: "14155550123",
                email: email,
                assignee: assignee,
                assigneeColor: "#7C3AED",
                status: status,
                value: value,
                lastActivity: lastActivity
            };
        }
        // Fallback
        return {
            id: leadId || "L-001",
            name: "Marcus Thompson",
            company: "Acme Corp",
            phone: "+1 (415) 555-0123",
            cleanPhone: "14155550123",
            email: "marcus@acmecorp.io",
            assignee: "Sarah Chen",
            assigneeColor: "#7C3AED",
            status: "Qualified",
            value: 24000,
            lastActivity: "2h ago"
        };
    }

    function getCleanPhone(lead) {
        if (lead.cleanPhone) return lead.cleanPhone.replace(/[^0-9]/g, '');
        if (lead.phone) return lead.phone.replace(/[^0-9]/g, '');
        return "";
    }

    function validateEmailFormat(email) {
        if (!email) return false;
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email.trim());
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add("show", "is-open");
            modal.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";
        }
    }

    window.closeCallChoiceModal = function(restoreFocus = true) {
        const modal = document.getElementById("callChoiceModal");
        if (modal) {
            modal.classList.remove("show", "is-open");
            modal.setAttribute("aria-hidden", "true");
        }
        document.body.style.overflow = "";
        if (restoreFocus && lastCommTriggerElement && typeof lastCommTriggerElement.focus === "function") {
            lastCommTriggerElement.focus();
            lastCommTriggerElement = null;
        }
    };

    window.closeWhatsAppModal = function(restoreFocus = true) {
        const modal = document.getElementById("whatsappModal");
        if (modal) {
            modal.classList.remove("show", "is-open");
            modal.setAttribute("aria-hidden", "true");
        }
        document.body.style.overflow = "";
        if (restoreFocus && lastCommTriggerElement && typeof lastCommTriggerElement.focus === "function") {
            lastCommTriggerElement.focus();
            lastCommTriggerElement = null;
        }
    };

    window.closeEmailModal = function(restoreFocus = true) {
        const modal = document.getElementById("emailModal");
        if (modal) {
            modal.classList.remove("show", "is-open");
            modal.setAttribute("aria-hidden", "true");
        }
        document.body.style.overflow = "";
        if (restoreFocus && lastCommTriggerElement && typeof lastCommTriggerElement.focus === "function") {
            lastCommTriggerElement.focus();
            lastCommTriggerElement = null;
        }
    };

    window.executeCallSoftphone = function() {
        closeCallChoiceModal(false);
        if (activeLeadForComm) {
            const contactObj = {
                id: activeLeadForComm.id,
                name: activeLeadForComm.name,
                company: activeLeadForComm.company,
                phone: activeLeadForComm.phone,
                email: activeLeadForComm.email,
                color: activeLeadForComm.assigneeColor || "#7C3AED",
                initials: activeLeadForComm.name.split(" ").map(n => n[0]).join("").toUpperCase(),
                status: activeLeadForComm.status || "Qualified"
            };

            if (window.softphone && typeof window.softphone.dialNumber === "function") {
                window.softphone.dialNumber(activeLeadForComm.phone, contactObj);
            } else if (window.softphone && typeof window.softphone.dialLead === "function") {
                window.softphone.dialLead(activeLeadForComm.id);
            }
        }
    };

    window.executeCallDevice = function() {
        if (!activeLeadForComm) return;
        closeCallChoiceModal(false);

        const phoneNum = activeLeadForComm.phone || activeLeadForComm.cleanPhone;
        if (!phoneNum) {
            showAlertModal({ title: "Phone Required", message: "No valid phone number is available for this lead.", type: "warning" });
            return;
        }

        const cleanPhone = getCleanPhone(activeLeadForComm);
        const telUrl = "tel:+" + cleanPhone;

        showCommToast("Opening your device's calling application…");

        setTimeout(() => {
            window.location.href = telUrl;
        }, 300);
    };

    window.selectWATemplateChip = function(chipBtn, tplKey) {
        document.querySelectorAll("#whatsappModal .template-chip").forEach(c => c.classList.remove("active"));
        if (chipBtn) chipBtn.classList.add("active");
        applyWATemplate(tplKey);
    };

    window.applyWATemplate = function(tplKey) {
        if (!activeLeadForComm) return;
        const textarea = document.getElementById("waMessageTextarea");
        if (!textarea) return;

        const leadName = activeLeadForComm.name;
        const firstName = leadName.split(" ")[0];
        const assignee = activeLeadForComm.assignee || "Sarah Chen";
        const company = activeLeadForComm.company || "NexFlow";

        let text = "";

        if (tplKey === "intro") {
            text = `Hello ${firstName}, this is ${assignee} from NexFlow. I’m following up regarding your enquiry with ${company}. Please let me know a convenient time to connect.`;
        } else if (tplKey === "followup") {
            text = `Hi ${firstName}, I wanted to follow up on our previous conversation regarding ${company}. Do you have 10 minutes this week for a quick update?`;
        } else if (tplKey === "meeting") {
            text = `Hi ${firstName}, confirming our scheduled call for ${company}. Looking forward to speaking with you!`;
        } else if (tplKey === "proposal") {
            text = `Hello ${firstName}, I sent over the proposal for ${company}. Please let me know if you have any questions.`;
        } else if (tplKey === "custom") {
            text = "";
        }

        textarea.value = text;
        updateWACharCount();
    };

    window.insertWAToken = function(tokenStr) {
        const textarea = document.getElementById("waMessageTextarea");
        if (!textarea) return;

        let replacement = tokenStr;
        if (activeLeadForComm) {
            if (tokenStr === "{first_name}") replacement = activeLeadForComm.name.split(" ")[0];
            if (tokenStr === "{company}") replacement = activeLeadForComm.company;
            if (tokenStr === "{representative}") replacement = activeLeadForComm.assignee || "Sarah Chen";
        }

        insertAtCursor(textarea, replacement);
        updateWACharCount();
    };

    window.updateWACharCount = function() {
        const textarea = document.getElementById("waMessageTextarea");
        const countEl = document.getElementById("waCharCount");
        const bubbleText = document.getElementById("waBubbleText");
        const bubbleTime = document.getElementById("waBubbleTime");

        if (textarea && countEl) {
            countEl.textContent = `${textarea.value.length} / 1000`;
        }

        if (textarea && bubbleText) {
            bubbleText.textContent = textarea.value.trim() || "(Message preview will appear here)";
        }

        if (bubbleTime) {
            const now = new Date();
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            bubbleTime.textContent = `${hours}:${minutes} ${ampm}`;
        }
    };

    window.executeContinueWhatsApp = function() {
        if (!activeLeadForComm) return;
        const cleanNum = getCleanPhone(activeLeadForComm);
        if (!cleanNum) {
            showAlertModal({ title: "WhatsApp Number Required", message: "No valid WhatsApp number is available for this lead.", type: "warning" });
            return;
        }

        const textarea = document.getElementById("waMessageTextarea");
        const rawMsg = textarea ? textarea.value.trim() : "";
        const encodedMsg = encodeURIComponent(rawMsg);

        const waUrl = `https://wa.me/${cleanNum}?text=${encodedMsg}`;

        closeWhatsAppModal(false);
        window.open(waUrl, "_blank", "noopener,noreferrer");
    };

    window.selectEmailTemplateChip = function(chipBtn, tplKey) {
        document.querySelectorAll("#emailModal .template-chip").forEach(c => c.classList.remove("active"));
        if (chipBtn) chipBtn.classList.add("active");
        applyEmailTemplate(tplKey);
    };

    window.applyEmailTemplate = function(tplKey) {
        if (!activeLeadForComm) return;
        const subjInput = document.getElementById("emailSubjectInput");
        const bodyArea = document.getElementById("emailBodyTextarea");
        if (!subjInput || !bodyArea) return;

        const leadName = activeLeadForComm.name;
        const firstName = leadName.split(" ")[0];
        const assignee = activeLeadForComm.assignee || "Sarah Chen";
        const company = activeLeadForComm.company || "NexFlow";

        let subj = "";
        let body = "";

        if (tplKey === "intro") {
            subj = `Introduction from NexFlow CRM`;
            body = `Dear ${firstName},\n\nThis is ${assignee} from NexFlow CRM. I am following up regarding your enquiry for ${company}.\n\nPlease let me know when you would be available for a brief discussion.\n\nBest regards,\n${assignee}`;
        } else if (tplKey === "followup") {
            subj = `Following up on our recent chat`;
            body = `Dear ${firstName},\n\nI wanted to follow up on our recent discussion regarding solutions for ${company}.\n\nLooking forward to hearing from you.\n\nBest regards,\n${assignee}`;
        } else if (tplKey === "meeting") {
            subj = `Meeting Confirmation - NexFlow CRM`;
            body = `Dear ${firstName},\n\nThank you for scheduling a time to speak about ${company}. We are looking forward to our upcoming call.\n\nBest regards,\n${assignee}`;
        } else if (tplKey === "proposal") {
            subj = `Proposal Review for ${company}`;
            body = `Dear ${firstName},\n\nI am following up on the proposal sent for ${company}. Please let me know if you have any questions or feedback.\n\nBest regards,\n${assignee}`;
        } else if (tplKey === "thanks") {
            subj = `Thank you for your time`;
            body = `Dear ${firstName},\n\nThank you for taking the time to speak today regarding ${company}.\n\nBest regards,\n${assignee}`;
        } else if (tplKey === "custom") {
            subj = "";
            body = "";
        }

        subjInput.value = subj;
        bodyArea.value = body;
        updateEmailStats();
    };

    window.insertEmailToken = function(tokenStr) {
        const bodyArea = document.getElementById("emailBodyTextarea");
        if (!bodyArea) return;

        let replacement = tokenStr;
        if (activeLeadForComm) {
            if (tokenStr === "{first_name}") replacement = activeLeadForComm.name.split(" ")[0];
            if (tokenStr === "{company}") replacement = activeLeadForComm.company;
            if (tokenStr === "{representative}") replacement = activeLeadForComm.assignee || "Sarah Chen";
        }

        insertAtCursor(bodyArea, replacement);
        updateEmailStats();
    };

    function insertAtCursor(el, textToInsert) {
        const start = el.selectionStart || el.value.length;
        const end = el.selectionEnd || el.value.length;
        const val = el.value;
        el.value = val.substring(0, start) + textToInsert + val.substring(end);
        el.selectionStart = el.selectionEnd = start + textToInsert.length;
        el.focus();
    }

    window.updateEmailStats = function() {
        const bodyArea = document.getElementById("emailBodyTextarea");
        const statsEl = document.getElementById("emailStatsText");
        if (bodyArea && statsEl) {
            const text = bodyArea.value.trim();
            const wordCount = text ? text.split(/\s+/).filter(Boolean).length : 0;
            const charCount = text.length;
            statsEl.textContent = `${wordCount} words • ${charCount} characters`;
        }
    };

    window.toggleEmailCcBcc = function() {
        const fields = document.getElementById("emailCcBccFields");
        if (fields) {
            fields.style.display = (fields.style.display === "none" || !fields.style.display) ? "block" : "none";
        }
    };

    window.saveEmailDraftSession = function() {
        const subj = document.getElementById("emailSubjectInput") ? document.getElementById("emailSubjectInput").value : "";
        const body = document.getElementById("emailBodyTextarea") ? document.getElementById("emailBodyTextarea").value : "";
        sessionStorage.setItem("NexFlow_email_draft", JSON.stringify({ subject: subj, body: body, time: Date.now() }));
        showCommToast("Draft saved successfully.");
    };

    window.executeOpenEmailApp = function() {
        if (!activeLeadForComm) return;
        const ccInput = document.getElementById("emailCcInput");
        const subjInput = document.getElementById("emailSubjectInput");
        const bodyArea = document.getElementById("emailBodyTextarea");

        const to = activeLeadForComm.email;
        if (!validateEmailFormat(to)) {
            showAlertModal({ title: "Email Required", message: "No valid email address is available for this lead.", type: "warning" });
            return;
        }

        const cc = ccInput ? ccInput.value.trim() : "";
        const subj = subjInput ? subjInput.value.trim() : "";
        const body = bodyArea ? bodyArea.value.trim() : "";

        let mailtoUrl = `mailto:${encodeURIComponent(to)}?subject=${encodeURIComponent(subj)}&body=${encodeURIComponent(body)}`;
        if (cc) {
            mailtoUrl += `&cc=${encodeURIComponent(cc)}`;
        }

        showCommToast("Opening your email application…");
        closeEmailModal(false);

        setTimeout(() => {
            window.location.href = mailtoUrl;
        }, 300);
    };

    function initCommunicationEvents() {
        document.querySelectorAll("#callChoiceModal, #whatsappModal, #emailModal").forEach(modal => {
            modal.addEventListener("click", function(e) {
                if (e.target === this) {
                    if (this.id === "callChoiceModal") closeCallChoiceModal();
                    if (this.id === "whatsappModal") closeWhatsAppModal();
                    if (this.id === "emailModal") closeEmailModal();
                }
            });
        });

        // ESC key to close open communication modals
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                if (document.getElementById("callChoiceModal")?.classList.contains("show")) {
                    closeCallChoiceModal();
                } else if (document.getElementById("whatsappModal")?.classList.contains("show")) {
                    closeWhatsAppModal();
                } else if (document.getElementById("emailModal")?.classList.contains("show")) {
                    closeEmailModal();
                }
            }
        });
    }

    window.showCommToast = function(msg) {
        let container = document.querySelector(".toast-container");
        if (!container) {
            container = document.createElement("div");
            container.className = "toast-container";
            container.style.cssText = "position:fixed;top:20px;right:20px;z-index:1200;display:flex;flex-direction:column;gap:8px;pointer-events:none;";
            document.body.appendChild(container);
        }

        const toast = document.createElement("div");
        toast.className = "toast-message";
        toast.style.cssText = "pointer-events:auto;background:#101828;color:#FFF;font-size:13px;font-weight:500;padding:10px 16px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);display:flex;align-items:center;gap:8px;animation:toastIn 0.2s ease-out;";
        toast.innerHTML = `<svg width="16" height="16" fill="none" stroke="#12B76A" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> <span>${msg}</span>`;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = "0";
            toast.style.transition = "opacity 0.25s ease";
            setTimeout(() => toast.remove(), 250);
        }, 2800);
    };
})();

