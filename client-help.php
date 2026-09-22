<?php
// client-help.php
require_once __DIR__ . '/includes/client-auth.php';
require_once __DIR__ . '/includes/client-helpers.php';

$client = require_client_auth();

$page_title = "Help & Support — NexFlow Client Portal";
$active_nav = "help";
$page_heading = "Help & Client Support Center";
include __DIR__ . '/includes/client-header.php';
include __DIR__ . '/includes/client-sidebar.php';
?>

<div class="client-portal-main-wrapper">
    <?php include __DIR__ . '/includes/client-topbar.php'; ?>

    <main class="client-portal-content">
        
        <div style="margin-bottom:20px;">
            <p style="font-size:13.5px;color:#64748B;margin:4px 0 0 0;">Find answers to frequently asked questions or open a direct support request with our technical solutions team.</p>
        </div>

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
            
            <!-- Left: FAQ Search & Accordion -->
            <div style="display:flex;flex-direction:column;gap:20px;">
                
                <!-- Search FAQ Box -->
                <div class="client-portal-card" style="padding:16px 20px;">
                    <div class="client-portal-search-wrapper" style="width:100%;">
                        <svg class="client-portal-search-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" class="client-portal-search-input" id="faqSearchInput" placeholder="Search questions (e.g. SLA, Invoices, SSO, Storage)..." oninput="filterFAQs()">
                    </div>
                </div>

                <!-- FAQ Accordion Card -->
                <div class="client-portal-card">
                    <div class="client-portal-card-header">
                        <h3 class="client-portal-card-title">Frequently Asked Questions</h3>
                    </div>
                    <div class="client-portal-card-body" id="faqAccordionContainer" style="padding:0;">
                        <!-- Injected by script -->
                    </div>
                </div>

            </div>

            <!-- Right: Submit Support Ticket -->
            <div style="display:flex;flex-direction:column;gap:20px;">
                
                <div class="client-portal-card">
                    <div class="client-portal-card-header">
                        <h3 class="client-portal-card-title">Submit a Support Ticket</h3>
                    </div>
                    <div class="client-portal-card-body">
                        <form id="supportTicketForm" onsubmit="submitSupportTicket(event)">
                            <div style="margin-bottom:14px;">
                                <label class="client-login-label">Category *</label>
                                <select class="input-control" id="ticketCategory">
                                    <option>Technical Support &amp; Integration</option>
                                    <option>Billing &amp; License True-up</option>
                                    <option>Onboarding &amp; Seat Training</option>
                                    <option>Account Administration</option>
                                </select>
                            </div>
                            <div style="margin-bottom:14px;">
                                <label class="client-login-label">Priority *</label>
                                <select class="input-control" id="ticketPriority">
                                    <option>Normal (Standard SLA)</option>
                                    <option>High (24-hour response)</option>
                                    <option>Urgent (Critical issue)</option>
                                </select>
                            </div>
                            <div style="margin-bottom:14px;">
                                <label class="client-login-label">Subject / Issue Summary *</label>
                                <input type="text" class="input-control" id="ticketSubject" placeholder="Brief summary of your request..." required>
                            </div>
                            <div style="margin-bottom:16px;"> 
                                <label class="client-login-label">Description *</label>
                                <textarea class="input-control" id="ticketDesc" rows="4" style="height:90px;resize:none;padding:8px 12px" placeholder="Provide details to help us investigate..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;">Submit Support Ticket</button>
                        </form>
                    </div>
                </div>

                <div class="client-portal-card" style="padding:16px;background:#F8FAFC;">
                    <div style="font-size:13px;font-weight:700;color:#0F172A;margin-bottom:4px;">Urgent Escalation?</div>
                    <p style="font-size:12.5px;color:#475569;margin:0 0 10px 0;line-height:1.4;">For critical SLA outages, contact your dedicated NexFlow account executive directly.</p>
                    <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientPortal.openQuickMessageModal('Urgent SLA Escalation')">
                        Escalate to Account Lead
                    </button>
                </div>

            </div>

        </div>

    </main>
</div>

<script>
const faqs = [
    {
        q: "How do I review and electronically sign our Enterprise MSA?",
        a: "Navigate to the 'My Deals' or 'Documents' section in your client portal. Click 'View Details' on your active contract to review the full Statement of Work, or download the PDF file to execute through your internal signing workflow."
    },
    {
        q: "How do I request additional CRM user seats for Acme Corp?",
        a: "Click 'Send Message' in the top bar to message Olivia Martin, or submit a ticket under 'Billing & License True-up'. We will provision additional staging licenses within 24 hours."
    },
    {
        q: "Where do I configure SAML 2.0 Single Sign-On?",
        a: "Review the 'Technical SSO & SAML Configuration Form' task in your Tasks tab. Upload your Identity Provider metadata XML file in the Documents section to complete setup."
    },
    {
        q: "What is the response time SLA for Enterprise Tier accounts?",
        a: "Enterprise tier clients receive dedicated 24/7 priority support with an initial response SLA of under 1 hour for urgent technical tickets."
    },
    {
        q: "How do I invite teammates to this Client Portal workspace?",
        a: "Send a message to your NexFlow account lead with the names and email addresses of team members who require access, and we will issue authenticated invitations."
    }
];

function filterFAQs() {
    const q = (document.getElementById("faqSearchInput")?.value || "").toLowerCase().trim();
    const container = document.getElementById("faqAccordionContainer");
    if (!container) return;

    const filtered = faqs.filter(f => !q || f.q.toLowerCase().includes(q) || f.a.toLowerCase().includes(q));

    if (filtered.length === 0) {
        container.innerHTML = '<div style="padding:24px;text-align:center;color:#64748B;font-size:13px;">No matching FAQs found.</div>';
        return;
    }

    container.innerHTML = filtered.map((f, idx) => `
        <div style="border-bottom:1px solid #F1F5F9;">
            <button type="button" style="width:100%;padding:14px 20px;background:none;border:none;text-align:left;font-size:13.5px;font-weight:600;color:#0F172A;cursor:pointer;display:flex;align-items:center;justify-content:space-between;" onclick="toggleFAQ(${idx})">
                <span>${f.q}</span>
                <svg id="faqIcon_${idx}" width="16" height="16" fill="none" stroke="#64748B" stroke-width="2" viewBox="0 0 24 24" style="transition:transform 0.2s;"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div id="faqAns_${idx}" style="display:none;padding:0 20px 14px 20px;font-size:13px;color:#475569;line-height:1.5;">
                ${f.a}
            </div>
        </div>
    `).join("");
}

function toggleFAQ(idx) {
    const ans = document.getElementById(`faqAns_${idx}`);
    const icon = document.getElementById(`faqIcon_${idx}`);
    if (!ans) return;
    const isShowing = ans.style.display === "block";
    ans.style.display = isShowing ? "none" : "block";
    if (icon) icon.style.transform = isShowing ? "rotate(0deg)" : "rotate(180deg)";
}

function submitSupportTicket(e) {
    e.preventDefault();
    const subject = document.getElementById("ticketSubject").value.trim();
    const category = document.getElementById("ticketCategory").value;
    const priority = document.getElementById("ticketPriority").value;
    if (!subject) return;

    window.clientPortal.data.tickets.unshift({
        id: "tkt-" + Date.now(),
        category: category,
        subject: subject,
        priority: priority,
        status: "Open",
        date: new Date().toISOString().split("T")[0]
    });

    try {
        localStorage.setItem("NexFlow_client_data", JSON.stringify(window.clientPortal.data));
    } catch (err) {}

    document.getElementById("supportTicketForm").reset();
    window.clientPortal.showToast(`Support Ticket #${Date.now().toString().slice(-4)} created successfully.`, "success");
}

document.addEventListener("DOMContentLoaded", filterFAQs);
</script>

<?php include __DIR__ . '/includes/client-footer.php'; ?>

