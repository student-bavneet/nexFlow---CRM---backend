/**
 * NexFlow CRM - Global Quick Add Controller & Modal Manager
 * Connects the top-bar "+ Quick Add" menu options to the existing create interfaces:
 * 1. New Lead
 * 2. New Contact
 * 3. New Company
 * 4. New Deal
 * 5. New Task
 * 
 * Works across ALL Admin pages.
 */

(function () {
    'use strict';

    // Storage Keys matching existing CRM modules
    const KEYS = {
        leads: 'NexFlow_leads_data_v2',
        contacts: 'NexFlow_contacts_data',
        companies: 'NexFlow_custom_companies',
        deals: 'NexFlow_pipeline_deals',
        tasks: 'NexFlow_tasks_data'
    };

    // Helper: Toast Notification
    function notify(message, type = 'success') {
        if (typeof showToast === 'function') {
            showToast(message, type);
            return;
        }
        if (typeof showCompaniesToast === 'function') {
            showCompaniesToast(message, type);
            return;
        }
        if (typeof showContactsToast === 'function') {
            showContactsToast(message, type);
            return;
        }

        let container = document.getElementById('quickAddToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'quickAddToastContainer';
            container.style.cssText = 'position:fixed; bottom:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:8px; pointer-events:none;';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.style.cssText = `
            background-color: ${type === 'danger' ? '#F04438' : '#059669'};
            color: #FFFFFF;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            pointer-events: auto;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(10px);
        `;
        toast.textContent = message;
        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        });

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    // Modal Manager DOM Injector
    function getModalOverlay() {
        let overlay = document.getElementById('globalQuickAddModal');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'globalQuickAddModal';
            overlay.className = 'modal-overlay';
            overlay.style.zIndex = '1150';
            overlay.onclick = function (e) {
                if (e.target === overlay) window.quickAddApp.close();
            };
            document.body.appendChild(overlay);
        }
        return overlay;
    }

    // Escape Key Dismissal Listener
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const menu = document.getElementById('quickAddMenu');
            if (menu) menu.classList.remove('show');
            window.quickAddApp.close();
        }
    });

    // Global Controller Object
    window.quickAddApp = {
        close: function () {
            const overlay = document.getElementById('globalQuickAddModal');
            if (overlay) {
                overlay.classList.remove('show');
                overlay.innerHTML = '';
            }
        },

        open: function (type) {
            // Close Quick Add dropdown menu immediately
            const menu = document.getElementById('quickAddMenu');
            if (menu) menu.classList.remove('show');

            const currentPath = window.location.pathname;

            // Route to Page Native Handler if on the specific module page
            if (type === 'lead' && currentPath.includes('leads.php') && typeof openAddLeadModal === 'function') {
                openAddLeadModal();
                return;
            }
            if (type === 'contact' && currentPath.includes('contacts.php') && window.contactsApp && typeof window.contactsApp.openAddModal === 'function') {
                window.contactsApp.openAddModal();
                return;
            }
            if (type === 'company' && currentPath.includes('companies.php') && window.companiesApp && typeof window.companiesApp.openAddDrawer === 'function') {
                window.companiesApp.openAddDrawer();
                return;
            }
            if (type === 'deal' && currentPath.includes('pipeline.php') && typeof openAddDealModal === 'function') {
                openAddDealModal('proposal');
                return;
            }
            if (type === 'task' && currentPath.includes('tasks.php') && window.tasksApp && typeof window.tasksApp.openCreateDrawer === 'function') {
                window.tasksApp.openCreateDrawer();
                return;
            }

            // Render Global Creation Modal for the entity
            const overlay = getModalOverlay();

            if (type === 'lead') {
                this.renderLeadModal(overlay);
            } else if (type === 'contact') {
                this.renderContactModal(overlay);
            } else if (type === 'company') {
                this.renderCompanyModal(overlay);
            } else if (type === 'deal') {
                this.renderDealModal(overlay);
            } else if (type === 'task') {
                this.renderTaskModal(overlay);
            }

            overlay.classList.add('show');
        },

        // 1. NEW LEAD MODAL
        renderLeadModal: function (overlay) {
            overlay.innerHTML = `
                <div class="modal-content" style="max-width: 520px;">
                    <div class="modal-header">
                        <h3 class="modal-title">Add New Lead</h3>
                        <button class="modal-close-btn" type="button" onclick="window.quickAddApp.close()">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form id="quickAddLeadForm" onsubmit="window.quickAddApp.submitLead(event)">
                        <div class="modal-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="input-control" id="qaLeadName" required placeholder="e.g. Alex Morgan">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" class="input-control" id="qaLeadEmail" required placeholder="alex@company.com">
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Company Name *</label>
                                    <input type="text" class="input-control" id="qaLeadCompany" required placeholder="e.g. Acme Corp">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Lead Status</label>
                                    <select class="input-control" id="qaLeadStatus">
                                        <option value="New" selected>New</option>
                                        <option value="Contacted">Contacted</option>
                                        <option value="Qualified">Qualified</option>
                                        <option value="Proposal">Proposal</option>
                                        <option value="Won">Won</option>
                                        <option value="Lost">Lost</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Lead Value ($)</label>
                                    <input type="number" class="input-control" id="qaLeadValue" placeholder="25000" value="25000">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Lead Source</label>
                                    <select class="input-control" id="qaLeadSource">
                                        <option value="Website" selected>Website</option>
                                        <option value="Referral">Referral</option>
                                        <option value="LinkedIn">LinkedIn</option>
                                        <option value="Inbound">Inbound</option>
                                        <option value="Outreach">Outreach</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">Assigned Owner</label>
                                <select class="input-control" id="qaLeadAssignee">
                                    <option value="Olivia Martin" selected>Olivia Martin</option>
                                    <option value="Sarah Chen">Sarah Chen</option>
                                    <option value="James Wu">James Wu</option>
                                    <option value="Michael Brown">Michael Brown</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-md" onclick="window.quickAddApp.close()">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-md">Add Lead</button>
                        </div>
                    </form>
                </div>
            `;
        },

        submitLead: function (e) {
            e.preventDefault();
            const name = document.getElementById('qaLeadName').value.trim();
            const email = document.getElementById('qaLeadEmail').value.trim();
            const company = document.getElementById('qaLeadCompany').value.trim();
            const status = document.getElementById('qaLeadStatus').value;
            const value = parseInt(document.getElementById('qaLeadValue').value) || 0;
            const source = document.getElementById('qaLeadSource').value;
            const assignee = document.getElementById('qaLeadAssignee').value;

            if (!name || !email || !company) return;

            const newId = 'L-' + String(Math.floor(Math.random() * 900) + 100);
            const initials = name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);

            const newRecord = {
                id: newId,
                name: name,
                email: email,
                phone: '+1 (555) 123-4567',
                company: company,
                status: status,
                score: 75,
                value: value,
                source: source,
                assignee: assignee,
                assigneeInitials: initials,
                assigneeColor: '#059669',
                createdAt: 'Just now',
                lastActivity: 'Just now'
            };

            // Save to LocalStorage
            try {
                const stored = JSON.parse(localStorage.getItem(KEYS.leads) || '[]');
                stored.unshift(newRecord);
                localStorage.setItem(KEYS.leads, JSON.stringify(stored));
            } catch (err) {
                console.warn('QuickAdd: Could not save lead:', err);
            }

            if (window.LEADS_MOCK_DATA) {
                window.LEADS_MOCK_DATA.unshift(newRecord);
            }

            if (window.NexFlowNotif) {
                window.NexFlowNotif.add({
                    type: 'lead',
                    title: `New lead: ${name}${company ? ' from ' + company : ''}`,
                    link: 'leads.php'
                });
            }

            this.close();
            if (typeof window.refreshSidebarBadges === 'function') {
                window.refreshSidebarBadges();
            }
            notify(`New lead "${name}" created successfully.`);
        },

        // 2. NEW CONTACT MODAL
        renderContactModal: function (overlay) {
            overlay.innerHTML = `
                <div class="modal-content" style="max-width: 540px;">
                    <div class="modal-header">
                        <h3 class="modal-title">Add New Contact</h3>
                        <button class="modal-close-btn" type="button" onclick="window.quickAddApp.close()">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form id="quickAddContactForm" onsubmit="window.quickAddApp.submitContact(event)">
                        <div class="modal-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" class="input-control" id="qaCntFirst" required placeholder="e.g. Marcus">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" class="input-control" id="qaCntLast" required placeholder="e.g. Thompson">
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Company Name *</label>
                                    <input type="text" class="input-control" id="qaCntCompany" required placeholder="e.g. Acme Corp">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Job Title</label>
                                    <input type="text" class="input-control" id="qaCntJob" placeholder="e.g. VP of Operations">
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" class="input-control" id="qaCntEmail" required placeholder="marcus@acme.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" class="input-control" id="qaCntPhone" placeholder="+1 (415) 555-0123">
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Relationship</label>
                                    <select class="input-control" id="qaCntRel">
                                        <option value="Prospect" selected>Prospect</option>
                                        <option value="Customer">Customer</option>
                                        <option value="Partner">Partner</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Assigned Owner</label>
                                    <select class="input-control" id="qaCntOwner">
                                        <option value="Olivia Martin" selected>Olivia Martin</option>
                                        <option value="Sarah Chen">Sarah Chen</option>
                                        <option value="James Wu">James Wu</option>
                                        <option value="Michael Brown">Michael Brown</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-md" onclick="window.quickAddApp.close()">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-md">Add Contact</button>
                        </div>
                    </form>
                </div>
            `;
        },

        submitContact: function (e) {
            e.preventDefault();
            const first = document.getElementById('qaCntFirst').value.trim();
            const last = document.getElementById('qaCntLast').value.trim();
            const company = document.getElementById('qaCntCompany').value.trim();
            const job = document.getElementById('qaCntJob').value.trim() || 'Executive';
            const email = document.getElementById('qaCntEmail').value.trim();
            const phone = document.getElementById('qaCntPhone').value.trim() || '+1 (555) 000-0000';
            const rel = document.getElementById('qaCntRel').value;
            const owner = document.getElementById('qaCntOwner').value;

            if (!first || !email || !company) return;

            const name = `${first} ${last}`;
            const newRecord = {
                id: 'CNT-' + String(Math.floor(Math.random() * 900) + 100),
                firstName: first,
                lastName: last,
                name: name,
                jobTitle: job,
                company: company,
                industry: 'Technology',
                relationship: rel,
                email: email,
                phone: phone,
                owner: owner,
                ownerInitials: owner.split(' ').map(n => n[0]).join(''),
                ownerColor: '#7C3AED',
                source: 'Direct',
                location: 'San Francisco, CA',
                dealsCount: 0,
                dealsValue: 0,
                lastActivity: 'Just now',
                lastActivityType: 'Created',
                createdAt: new Date().toISOString().split('T')[0],
                notes: [], tasks: [], deals: [], activities: []
            };

            try {
                const stored = JSON.parse(localStorage.getItem(KEYS.contacts) || '[]');
                stored.unshift(newRecord);
                localStorage.setItem(KEYS.contacts, JSON.stringify(stored));
            } catch (err) {
                console.warn('QuickAdd: Could not save contact:', err);
            }

            if (window.NexFlowNotif) {
                window.NexFlowNotif.add({
                    type: 'contact',
                    title: `New contact: ${name}`,
                    link: 'contacts.php'
                });
            }

            this.close();
            notify(`Contact "${name}" created successfully.`);
        },

        // 3. NEW COMPANY MODAL
        renderCompanyModal: function (overlay) {
            overlay.innerHTML = `
                <div class="modal-content" style="max-width: 540px;">
                    <div class="modal-header">
                        <h3 class="modal-title">Add New Company</h3>
                        <button class="modal-close-btn" type="button" onclick="window.quickAddApp.close()">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form id="quickAddCompanyForm" onsubmit="window.quickAddApp.submitCompany(event)">
                        <div class="modal-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Company Name *</label>
                                    <input type="text" class="input-control" id="qaCompName" required placeholder="e.g. Acme Corp">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Website Domain</label>
                                    <input type="text" class="input-control" id="qaCompDomain" placeholder="acme.com">
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Industry</label>
                                    <select class="input-control" id="qaCompIndustry">
                                        <option value="Technology & SaaS" selected>Technology &amp; SaaS</option>
                                        <option value="Financial Services">Financial Services</option>
                                        <option value="E-Commerce">E-Commerce</option>
                                        <option value="Healthcare">Healthcare</option>
                                        <option value="Marketing">Marketing</option>
                                        <option value="Logistics">Logistics</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Company Size</label>
                                    <select class="input-control" id="qaCompSize">
                                        <option value="1-10 employees">1-10 employees</option>
                                        <option value="11-50 employees">11-50 employees</option>
                                        <option value="51-200 employees" selected>51-200 employees</option>
                                        <option value="251-500 employees">251-500 employees</option>
                                        <option value="500+ employees">500+ employees</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Relationship</label>
                                    <select class="input-control" id="qaCompRel">
                                        <option value="Prospect" selected>Prospect</option>
                                        <option value="Customer">Customer</option>
                                        <option value="Partner">Partner</option>
                                        <option value="Vendor">Vendor</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Account Owner</label>
                                    <select class="input-control" id="qaCompOwner">
                                        <option value="Sarah Chen" selected>Sarah Chen</option>
                                        <option value="Olivia Martin">Olivia Martin</option>
                                        <option value="James Wu">James Wu</option>
                                        <option value="Michael Brown">Michael Brown</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-md" onclick="window.quickAddApp.close()">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-md">Add Company</button>
                        </div>
                    </form>
                </div>
            `;
        },

        submitCompany: function (e) {
            e.preventDefault();
            const name = document.getElementById('qaCompName').value.trim();
            const domain = document.getElementById('qaCompDomain').value.trim() || `${name.toLowerCase().replace(/[^a-z]/g, '')}.com`;
            const industry = document.getElementById('qaCompIndustry').value;
            const size = document.getElementById('qaCompSize').value;
            const rel = document.getElementById('qaCompRel').value;
            const owner = document.getElementById('qaCompOwner').value;

            if (!name) return;

            const initials = name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
            const newRecord = {
                id: `COMP-${Date.now().toString().slice(-3)}`,
                name: name,
                domain: domain,
                logoInitials: initials,
                logoColor: '#7C3AED',
                industry: industry,
                size: size,
                annualRevenue: '$25.0M',
                relationship: rel,
                location: 'San Francisco, CA',
                primaryContactName: 'Marcus Thompson',
                owner: owner,
                ownerInitials: owner.split(' ').map(n => n[0]).join('').toUpperCase(),
                ownerColor: '#7C3AED',
                contactsCount: 1,
                openDealsCount: 1,
                openDealsValue: 25000,
                lastActivity: 'Just now',
                lastActivityType: 'Created',
                createdAt: 'Today'
            };

            try {
                const stored = JSON.parse(localStorage.getItem(KEYS.companies) || '[]');
                stored.unshift(newRecord);
                localStorage.setItem(KEYS.companies, JSON.stringify(stored));
            } catch (err) {
                console.warn('QuickAdd: Could not save company:', err);
            }

            if (window.NexFlowNotif) {
                window.NexFlowNotif.add({
                    type: 'company',
                    title: `New company: ${name}`,
                    link: 'companies.php'
                });
            }

            this.close();
            notify(`Company "${name}" created successfully.`);
        },

        // 4. NEW DEAL MODAL
        renderDealModal: function (overlay) {
            overlay.innerHTML = `
                <div class="modal-content" style="max-width: 560px;">
                    <div class="modal-header">
                        <h3 class="modal-title">Create New Deal</h3>
                        <button class="modal-close-btn" type="button" onclick="window.quickAddApp.close()">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form id="quickAddDealForm" onsubmit="window.quickAddApp.submitDeal(event)">
                        <div class="modal-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Deal Name *</label>
                                    <input type="text" class="input-control" id="qaDealName" required placeholder="e.g. Acme Corp Enterprise License">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Associated Contact</label>
                                    <input type="text" class="input-control" id="qaDealContact" placeholder="Marcus Thompson">
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Company Name *</label>
                                    <input type="text" class="input-control" id="qaDealCompany" required placeholder="e.g. Acme Corp">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Deal Value ($) *</label>
                                    <input type="number" class="input-control" id="qaDealValue" required placeholder="25000" value="25000">
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Pipeline Stage</label>
                                    <select class="input-control" id="qaDealStage">
                                        <option value="prospect">Prospect</option>
                                        <option value="qualified">Qualified</option>
                                        <option value="proposal" selected>Proposal</option>
                                        <option value="negotiation">Negotiation</option>
                                        <option value="closed_won">Closed Won</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Probability (%)</label>
                                    <input type="number" class="input-control" id="qaDealProb" min="0" max="100" value="75">
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Expected Close Date</label>
                                    <input type="date" class="input-control" id="qaDealDate" value="${new Date(Date.now() + 30*86400000).toISOString().split('T')[0]}">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Deal Owner</label>
                                    <select class="input-control" id="qaDealOwner">
                                        <option value="Sarah Chen" selected>Sarah Chen</option>
                                        <option value="James Wu">James Wu</option>
                                        <option value="Olivia Martin">Olivia Martin</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-md" onclick="window.quickAddApp.close()">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-md">Add Deal</button>
                        </div>
                    </form>
                </div>
            `;
        },

        submitDeal: function (e) {
            e.preventDefault();
            const name = document.getElementById('qaDealName').value.trim();
            const contact = document.getElementById('qaDealContact').value.trim() || 'Marcus Thompson';
            const company = document.getElementById('qaDealCompany').value.trim();
            const value = parseInt(document.getElementById('qaDealValue').value) || 0;
            const stage = document.getElementById('qaDealStage').value;
            const prob = parseInt(document.getElementById('qaDealProb').value) || 75;
            const closeDate = document.getElementById('qaDealDate').value;
            const owner = document.getElementById('qaDealOwner').value;

            if (!name || !company) return;

            const newRecord = {
                id: 'DEAL-' + String(Math.floor(Math.random() * 900) + 100),
                name: name,
                contact: contact,
                company: company,
                value: value,
                stage: stage,
                prob: prob,
                closeDate: closeDate,
                isoCloseDate: closeDate,
                closeDateText: closeDate,
                source: 'Website',
                tag: 'Website',
                owner: owner,
                initials: owner.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase(),
                color: '#7C3AED',
                priority: 'High'
            };

            try {
                const stored = JSON.parse(localStorage.getItem(KEYS.deals) || '[]');
                stored.unshift(newRecord);
                localStorage.setItem(KEYS.deals, JSON.stringify(stored));
            } catch (err) {
                console.warn('QuickAdd: Could not save deal:', err);
            }

            if (window.NexFlowNotif) {
                window.NexFlowNotif.add({
                    type: 'deal',
                    title: `New deal: ${name}`,
                    link: 'pipeline.php'
                });
            }

            this.close();
            notify(`New deal "${name}" created successfully.`);
        },

        // 5. NEW TASK MODAL
        renderTaskModal: function (overlay) {
            overlay.innerHTML = `
                <div class="modal-content" style="max-width: 540px;">
                    <div class="modal-header">
                        <h3 class="modal-title">Create Task</h3>
                        <button class="modal-close-btn" type="button" onclick="window.quickAddApp.close()">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form id="quickAddTaskForm" onsubmit="window.quickAddApp.submitTask(event)">
                        <div class="modal-body">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label class="form-label">Task Title *</label>
                                <input type="text" class="input-control input-sm" id="qaTaskTitle" required placeholder="e.g. Follow up with Marcus Thompson">
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Task Type</label>
                                    <select class="input-control input-sm" id="qaTaskType">
                                        <option value="Call">Call</option>
                                        <option value="Email">Email</option>
                                        <option value="WhatsApp">WhatsApp</option>
                                        <option value="Meeting">Meeting</option>
                                        <option value="Follow-up">Follow-up</option>
                                        <option value="Proposal">Proposal</option>
                                        <option value="General Task" selected>General Task</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Priority</label>
                                    <select class="input-control input-sm" id="qaTaskPriority">
                                        <option value="Urgent">Urgent</option>
                                        <option value="High" selected>High</option>
                                        <option value="Medium">Medium</option>
                                        <option value="Low">Low</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label class="form-label">Description</label>
                                <textarea class="input-control" id="qaTaskDesc" style="height: 54px; font-size: 12px; resize: none;" placeholder="Task details and instructions..."></textarea>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Related Contact</label>
                                    <input type="text" class="input-control input-sm" id="qaTaskContact" placeholder="Marcus Thompson">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Related Company</label>
                                    <input type="text" class="input-control input-sm" id="qaTaskCompany" placeholder="Acme Corp">
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Due Date & Time</label>
                                    <input type="text" class="input-control input-sm" id="qaTaskDue" placeholder="Today, 10:30 AM" value="Today, 10:30 AM">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Assigned Owner</label>
                                    <select class="input-control input-sm" id="qaTaskAssignee">
                                        <option value="Sarah Chen" selected>Sarah Chen</option>
                                        <option value="James Wu">James Wu</option>
                                        <option value="Olivia Martin">Olivia Martin</option>
                                    </select>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #F8FAFC; border: 1px solid var(--border-divider); border-radius: var(--radius-md); margin-top: 10px;">
                                <input type="checkbox" id="qaTaskClientVisible" class="input-checkbox" style="width: 16px; height: 16px; cursor: pointer;">
                                <div>
                                    <label for="qaTaskClientVisible" style="font-size: 12.5px; font-weight: 600; color: var(--text-heading); cursor: pointer; margin: 0;">Client Visible (Display in Client Portal)</label>
                                    <div style="font-size: 11px; color: var(--text-muted);">Enable to make this task visible in the Client Portal checklist.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="window.quickAddApp.close()">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Save Task</button>
                        </div>
                    </form>
                </div>
            `;
        },

        submitTask: function (e) {
            e.preventDefault();
            const title = document.getElementById('qaTaskTitle').value.trim();
            const type = document.getElementById('qaTaskType').value;
            const priority = document.getElementById('qaTaskPriority').value;
            const desc = document.getElementById('qaTaskDesc').value.trim();
            const contact = document.getElementById('qaTaskContact').value.trim() || 'Marcus Thompson';
            const company = document.getElementById('qaTaskCompany').value.trim() || 'Acme Corp';
            const due = document.getElementById('qaTaskDue').value.trim() || 'Today, 10:30 AM';
            const assignee = document.getElementById('qaTaskAssignee').value;
            const clientVisible = document.getElementById('qaTaskClientVisible').checked;

            if (!title) return;

            const newRecord = {
                id: 'TASK-' + String(Math.floor(Math.random() * 900) + 100),
                title: title,
                type: type,
                priority: priority,
                status: 'Pending',
                due: due,
                dueDate: new Date().toISOString().split('T')[0],
                assignee: assignee,
                assigneeInitials: assignee.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase(),
                assigneeColor: '#2563EB',
                contact: contact,
                company: company,
                deal: 'Enterprise Expansion',
                description: desc,
                clientVisible: clientVisible,
                createdAt: 'Today'
            };

            try {
                const stored = JSON.parse(localStorage.getItem(KEYS.tasks) || '[]');
                stored.unshift(newRecord);
                localStorage.setItem(KEYS.tasks, JSON.stringify(stored));
            } catch (err) {
                console.warn('QuickAdd: Could not save task:', err);
            }

            if (window.NexFlowNotif) {
                window.NexFlowNotif.add({
                    type: 'task',
                    title: `Task due: ${title}`,
                    link: 'tasks.php'
                });
            }

            this.close();
            if (typeof window.refreshSidebarBadges === 'function') {
                window.refreshSidebarBadges();
            }
            notify(`Task "${title}" created successfully.`);
        }
    };
})();

