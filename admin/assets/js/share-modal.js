/**
 * NexFlow CRM — Global Reusable Share Controller
 * Handles contextual record sharing for Leads, Deals, Contacts, Companies,
 * Reports, Subscriptions, Contracts, and Estimate Requests.
 * Stacking: z-index 1100 (above drawers 1010), persists to localStorage.
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'NexFlow_share_settings_v1';

    // Mock Team Members Data
    const mockTeam = [
        { id: 'usr-1', name: 'Olivia Martin', role: 'Sales Manager', email: 'olivia@NexFlow.io', initials: 'OM', color: '#7C3AED' },
        { id: 'usr-2', name: 'Sarah Chen', role: 'Account Executive', email: 'sarah.chen@NexFlow.io', initials: 'SC', color: '#7C3AED' },
        { id: 'usr-3', name: 'James Wu', role: 'Senior Sales Rep', email: 'james.wu@NexFlow.io', initials: 'JW', color: '#0284C7' },
        { id: 'usr-4', name: 'Daniel Reyes', role: 'Partnerships Lead', email: 'daniel.reyes@NexFlow.io', initials: 'DR', color: '#0284C7' },
        { id: 'usr-5', name: 'Elena Petrova', role: 'Solutions Architect', email: 'elena@NexFlow.io', initials: 'EP', color: '#059669' }
    ];

    let currentRecord = null;
    let shareSettingsMap = loadAllShareSettings();
    let lastTriggerElement = null;

    function loadAllShareSettings() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) return JSON.parse(saved);
        } catch (e) {
            console.warn('Could not load share settings:', e);
        }
        return {};
    }

    function saveAllShareSettings() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(shareSettingsMap));
        } catch (e) {
            console.warn('Could not save share settings:', e);
        }
    }

    window.NexFlowShare = {
        init: function () {
            this.setupListeners();
        },

        setupListeners: function () {
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('NexFlowShareModal');
                    if (modal && modal.classList.contains('show')) {
                        window.NexFlowShare.closeModal();
                        e.stopPropagation();
                    }
                }
            });
        },

        openReportShare: function (triggerBtn) {
            this.openShareModal({
                type: 'Report',
                id: 'report-overview',
                name: 'Reports & Analytics Dashboard',
                company: 'Executive Summary'
            }, triggerBtn);
        },

        /**
         * Open Share Modal for any record
         * @param {Object} options { type, id, name, company, meta }
         * @param {HTMLElement} triggerBtn
         */
        openShareModal: function (options, triggerBtn) {
            if (!options) return;
            lastTriggerElement = triggerBtn || document.activeElement;
            currentRecord = options;

            const modal = document.getElementById('NexFlowShareModal');
            const typeBadge = document.getElementById('shareModalRecordType');
            const titleEl = document.getElementById('shareModalRecordTitle');
            const subEl = document.getElementById('shareModalRecordSub');
            const linkInput = document.getElementById('shareModalLinkInput');
            const nativeBtn = document.getElementById('btnShareNative');

            if (typeBadge) typeBadge.textContent = options.type || 'Record';
            if (titleEl) titleEl.textContent = `Share ${options.type}: ${options.name || options.id}`;
            if (subEl) subEl.textContent = `${options.company ? options.company + ' • ' : ''}ID: ${options.id}`;

            // Generate Deterministic Demo URL
            const url = new URL(window.location.href);
            url.searchParams.set('shared', (options.type || 'record').toLowerCase().replace(/\s+/g, '-'));
            url.searchParams.set('id', options.id);
            if (options.meta) {
                Object.keys(options.meta).forEach(k => {
                    url.searchParams.set(`share_${k}`, options.meta[k]);
                });
            }
            const generatedLink = url.toString();
            if (linkInput) linkInput.value = generatedLink;

            // Native Share Check
            if (nativeBtn) {
                if (navigator.share) nativeBtn.style.display = 'inline-flex';
                else nativeBtn.style.display = 'none';
            }

            // Restore Saved Settings for this specific record if any
            const recordKey = `${(options.type || '').toLowerCase()}_${options.id}`;
            const existingSettings = shareSettingsMap[recordKey] || {
                audience: 'anyone',
                permission: 'view',
                expiry: 'never',
                message: '',
                selectedMembers: []
            };

            const audSelect = document.getElementById('shareAudienceSelect');
            const permSelect = document.getElementById('sharePermissionSelect');
            const expSelect = document.getElementById('shareExpirySelect');
            const msgArea = document.getElementById('shareMessageTextarea');

            if (audSelect) audSelect.value = existingSettings.audience || 'anyone';
            if (permSelect) permSelect.value = existingSettings.permission || 'view';
            if (expSelect) expSelect.value = existingSettings.expiry || 'never';
            if (msgArea) msgArea.value = existingSettings.message || '';

            this.toggleAudienceSection();
            this.renderTeamList(existingSettings.selectedMembers || []);
            this.renderAccessList(recordKey);

            if (modal) {
                modal.style.display = 'flex';
                modal.classList.add('show');
            }
        },

        closeModal: function () {
            const modal = document.getElementById('NexFlowShareModal');
            if (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
            }
            if (lastTriggerElement && typeof lastTriggerElement.focus === 'function') {
                lastTriggerElement.focus();
            }
        },

        toggleAudienceSection: function () {
            const aud = document.getElementById('shareAudienceSelect')?.value;
            const sec = document.getElementById('shareTeamSection');
            if (sec) {
                sec.style.display = (aud === 'team') ? 'block' : 'none';
            }
        },

        filterTeamList: function (query) {
            const q = (query || '').toLowerCase().trim();
            document.querySelectorAll('#shareTeamList .share-team-item').forEach(item => {
                const name = item.getAttribute('data-name') || '';
                item.style.display = (!q || name.includes(q)) ? 'flex' : 'none';
            });
        },

        renderTeamList: function (selectedIds) {
            const container = document.getElementById('shareTeamList');
            if (!container) return;

            const selSet = new Set(selectedIds || []);
            container.innerHTML = mockTeam.map(user => `
                <div class="share-team-item" data-name="${user.name.toLowerCase()}">
                    <label>
                        <input type="checkbox" class="input-checkbox share-team-cb" value="${user.id}" ${selSet.has(user.id) ? 'checked' : ''}>
                        <span class="avatar avatar-xs" style="background-color:${user.color};font-size:9px;">${user.initials}</span>
                        <span>${user.name} <small style="color:var(--text-muted);">(${user.role})</small></span>
                    </label>
                </div>
            `).join('');
        },

        renderAccessList: function (recordKey) {
            const container = document.getElementById('shareAccessList');
            if (!container) return;

            const existing = shareSettingsMap[recordKey];
            if (!existing) {
                container.innerHTML = `
                    <div class="share-access-item">
                        <div class="share-access-item-meta">
                            <span style="color:var(--text-secondary);">Default public link access</span>
                        </div>
                        <span class="badge badge-success" style="font-size:10px;">Public Demo</span>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div class="share-access-item">
                    <div class="share-access-item-meta">
                        <div>
                            <strong style="color:var(--text-heading);">${existing.audience === 'team' ? 'Selected Team Members' : 'Anyone with link'}</strong>
                            <div style="font-size:11px;color:var(--text-muted);">Permission: ${existing.permission} • Expiry: ${existing.expiry}</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-ghost btn-xs" style="color:#DC2626;" onclick="window.NexFlowShare.removeAccess('${recordKey}')">Remove Access</button>
                </div>
            `;
        },

        removeAccess: function (recordKey) {
            delete shareSettingsMap[recordKey];
            saveAllShareSettings();
            this.renderAccessList(recordKey);
            this.showToast('Access grant removed.', 'info');
        },

        copyLink: function () {
            const linkInput = document.getElementById('shareModalLinkInput');
            if (!linkInput) return;
            const text = linkInput.value;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    this.showToast('Link copied to clipboard.', 'success');
                }).catch(() => {
                    this.fallbackCopy(text);
                });
            } else {
                this.fallbackCopy(text);
            }
        },

        fallbackCopy: function (text) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                this.showToast('Link copied to clipboard.', 'success');
            } catch (err) {
                this.showToast('Could not copy link.', 'error');
            }
            document.body.removeChild(ta);
        },

        nativeShare: function () {
            if (!currentRecord) return;
            const link = document.getElementById('shareModalLinkInput')?.value || window.location.href;
            if (navigator.share) {
                navigator.share({
                    title: `NexFlow CRM - ${currentRecord.type}: ${currentRecord.name}`,
                    text: `Review ${currentRecord.type} for ${currentRecord.company || currentRecord.name} on NexFlow CRM`,
                    url: link
                }).catch(err => {
                    if (err.name !== 'AbortError') this.showToast('Share dialog closed.', 'info');
                });
            } else {
                this.copyLink();
            }
        },

        saveSettings: function () {
            if (!currentRecord) return;
            const recordKey = `${(currentRecord.type || '').toLowerCase()}_${currentRecord.id}`;
            const audience = document.getElementById('shareAudienceSelect')?.value || 'anyone';
            const permission = document.getElementById('sharePermissionSelect')?.value || 'view';
            const expiry = document.getElementById('shareExpirySelect')?.value || 'never';
            const message = document.getElementById('shareMessageTextarea')?.value.trim() || '';

            const selectedMembers = [];
            document.querySelectorAll('#shareTeamList .share-team-cb:checked').forEach(cb => {
                selectedMembers.push(cb.value);
            });

            shareSettingsMap[recordKey] = {
                recordType: currentRecord.type,
                recordId: currentRecord.id,
                recordName: currentRecord.name,
                company: currentRecord.company,
                audience: audience,
                permission: permission,
                expiry: expiry,
                message: message,
                selectedMembers: selectedMembers,
                updatedAt: new Date().toISOString()
            };

            saveAllShareSettings();
            this.closeModal();
            this.showToast('Sharing preferences saved locally.', 'success');
        },

        showToast: function (msg, type = 'info') {
            const container = document.getElementById('shareToastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `share-toast ${type}`;
            toast.innerHTML = `
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                <span>${msg}</span>
            `;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.2s ease';
                setTimeout(() => toast.remove(), 200);
            }, 3000);
        },

        // Helper contextual trigger functions for all 8 locations
        openLeadShare: function (leadId, btn) {
            let lead = null;
            if (window.LEADS_MOCK_DATA && Array.isArray(window.LEADS_MOCK_DATA)) {
                lead = window.LEADS_MOCK_DATA.find(l => l.id === leadId);
            }
            if (!lead && typeof findLead === 'function') lead = findLead(leadId);
            if (!lead) {
                const title = document.getElementById('drawerLeadTitle')?.textContent || 'Marcus Thompson';
                lead = { id: leadId || 'L-001', name: title, company: 'Acme Corp' };
            }
            this.openShareModal({
                type: 'Lead',
                id: lead.id,
                name: lead.name,
                company: lead.company
            }, btn);
        },

        openDealShare: function (dealId, btn) {
            let name = 'Deal Details';
            let company = 'Sales Pipeline';
            const titleEl = document.getElementById('dealDrawerTitle');
            const subEl = document.getElementById('dealDrawerBreadcrumb');
            if (titleEl) name = titleEl.textContent;
            if (subEl) company = subEl.textContent;

            this.openShareModal({
                type: 'Deal',
                id: dealId || 'deal-001',
                name: name,
                company: company
            }, btn);
        },

        openContactShare: function (contactId, btn) {
            let name = 'Contact Details';
            let company = 'Contacts';
            const titleEl = document.getElementById('drawerContactTitle');
            if (titleEl) name = titleEl.textContent;

            this.openShareModal({
                type: 'Contact',
                id: contactId || 'CNT-001',
                name: name,
                company: company
            }, btn);
        },

        openCompanyShare: function (companyId, btn) {
            let name = 'Company Details';
            const titleEl = document.getElementById('compHeaderTitle');
            const subEl = document.getElementById('compBreadcrumbName');
            if (subEl) name = subEl.textContent;
            else if (titleEl) name = titleEl.textContent;

            this.openShareModal({
                type: 'Company',
                id: companyId || 'comp-001',
                name: name,
                company: name
            }, btn);
        },

        openReportShare: function (btn) {
            const activeTab = document.querySelector('.reports-tab.active')?.getAttribute('data-tab') || 'overview';
            const dateRange = document.getElementById('reportsDateRangeDropdown')?.textContent?.trim() || 'This Quarter';
            this.openShareModal({
                type: 'Report',
                id: `report-${activeTab}`,
                name: `Sales Analytics (${activeTab.toUpperCase()})`,
                company: 'NexFlow Analytics',
                meta: { tab: activeTab, period: dateRange }
            }, btn);
        },

        openSubscriptionShare: function (subId, btn) {
            let name = 'Subscription Details';
            let company = 'Subscriptions';
            const titleEl = document.getElementById('subDrawerTitle');
            const subEl = document.getElementById('subDrawerCompany');
            if (titleEl) name = titleEl.textContent;
            if (subEl) company = subEl.textContent;

            this.openShareModal({
                type: 'Subscription',
                id: subId || 'SUB-2026-101',
                name: name,
                company: company
            }, btn);
        },

        openContractShare: function (contractId, btn) {
            let name = 'Contract Details';
            let company = 'Contracts';
            const titleEl = document.getElementById('cntDrawerTitle');
            const subEl = document.getElementById('cntDrawerSubtitle');
            if (titleEl) name = titleEl.textContent;
            if (subEl) company = subEl.textContent;

            this.openShareModal({
                type: 'Contract',
                id: contractId || 'MSA-2026-ACME',
                name: name,
                company: company
            }, btn);
        },

        openEstimateRequestShare: function (reqId, btn) {
            let name = 'Estimate Request Details';
            let company = 'Estimate Requests';
            const titleEl = document.getElementById('estDrawerTitle');
            const subEl = document.getElementById('estDrawerSubtitle');
            if (titleEl) name = titleEl.textContent;
            if (subEl) company = subEl.textContent;

            this.openShareModal({
                type: 'Estimate Request',
                id: reqId || 'REQ-2026-101',
                name: name,
                company: company
            }, btn);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.NexFlowShare.init());
    } else {
        window.NexFlowShare.init();
    }
})();

