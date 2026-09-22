/**
 * NexFlow CRM — Admin Header Global Search JavaScript Controller
 * Performs client-side search across Leads, Contacts, Companies, Deals, Projects,
 * Tasks, Invoices, Contracts, Proposals, Estimates, and Documents.
 */

(function () {
    'use strict';

    const STORAGE_KEYS = {
        leads: 'NexFlow_leads_data_v2',
        contacts: 'NexFlow_contacts_data',
        companies: 'NexFlow_custom_companies',
        deals: 'NexFlow_pipeline_deals',
        projects: 'NexFlow_projects_v1',
        tasks: 'NexFlow_tasks_data',
        invoices: 'NexFlow_invoices_v1',
        contracts: 'NexFlow_contracts_v1',
        proposals: 'NexFlow_proposals_v1',
        estimates: 'NexFlow_estimates_v1',
        documents: 'NexFlow_documents_v1'
    };

    // Default Fallback Demo Records
    const defaultData = {
        leads: [
            { id: 'L-101', name: 'Marcus Thompson', company: 'Acme Corp', type: 'Lead', link: 'leads.php' },
            { id: 'L-102', name: 'Victoria Chen', company: 'Synapse AI', type: 'Lead', link: 'leads.php' },
            { id: 'L-103', name: 'Sarah Jenkins', company: 'Nexus Technologies', type: 'Lead', link: 'leads.php' },
            { id: 'L-104', name: 'David Miller', company: 'Apex Global', type: 'Lead', link: 'leads.php' }
        ],
        contacts: [
            { id: 'C-101', name: 'Marcus Thompson', meta: 'VP of Technology • Acme Corp', type: 'Contact', link: 'contacts.php' },
            { id: 'C-102', name: 'Victoria Chen', meta: 'Chief AI Officer • Synapse AI', type: 'Contact', link: 'contacts.php' },
            { id: 'C-103', name: 'Elena Rostova', meta: 'Director of IT • CloudShift Operations', type: 'Contact', link: 'contacts.php' },
            { id: 'C-104', name: 'Michael Vance', meta: 'Head of Infrastructure • Fortis Group', type: 'Contact', link: 'contacts.php' }
        ],
        companies: [
            { id: 'COM-101', name: 'Acme Corp', meta: 'Enterprise SaaS • 250 Employees', type: 'Company', link: 'companies.php' },
            { id: 'COM-102', name: 'Synapse AI', meta: 'AI Solutions • 120 Employees', type: 'Company', link: 'companies.php' },
            { id: 'COM-103', name: 'BrightPath Analytics', meta: 'Data Intelligence • 85 Employees', type: 'Company', link: 'companies.php' },
            { id: 'COM-104', name: 'Fortis Group', meta: 'Financial Tech • 500 Employees', type: 'Company', link: 'companies.php' }
        ],
        deals: [
            { id: 'DEAL-101', name: 'Enterprise Expansion', meta: 'Deal • Acme Corp ($145,000)', type: 'Deal', link: 'pipeline.php' },
            { id: 'DEAL-102', name: 'CloudShift Enterprise Migration', meta: 'Deal • CloudShift ($85,000)', type: 'Deal', link: 'pipeline.php' },
            { id: 'DEAL-103', name: 'AI Engine Integration', meta: 'Deal • Synapse AI ($62,000)', type: 'Deal', link: 'pipeline.php' }
        ],
        projects: [
            { id: 'PRJ-101', name: 'E-Commerce Platform', meta: 'Project • Acme Corp', type: 'Project', link: 'projects.php' },
            { id: 'PRJ-102', name: 'Mobile App Redesign', meta: 'Project • BrightPath Analytics', type: 'Project', link: 'projects.php' },
            { id: 'PRJ-103', name: 'Cloud Infrastructure Upgrade', meta: 'Project • Fortis Group', type: 'Project', link: 'projects.php' }
        ],
        tasks: [
            { id: 'TSK-101', name: 'Prepare technical SLA draft', meta: 'Task • Fortis Group', type: 'Task', link: 'tasks.php' },
            { id: 'TSK-102', name: 'Demo call with BrightPath Analytics', meta: 'Task • BrightPath', type: 'Task', link: 'tasks.php' },
            { id: 'TSK-103', name: 'Follow up with Marcus Thompson', meta: 'Task • Acme Corp', type: 'Task', link: 'tasks.php' }
        ],
        invoices: [
            { id: 'INV-2026-008', name: 'INV-2026-008', meta: 'Invoice • Acme Corp ($12,500)', type: 'Invoice', link: 'invoices.php' },
            { id: 'INV-2026-007', name: 'INV-2026-007', meta: 'Invoice • BrightPath Analytics ($8,400)', type: 'Invoice', link: 'invoices.php' },
            { id: 'INV-2026-006', name: 'INV-2026-006', meta: 'Invoice • Fortis Group ($18,200)', type: 'Invoice', link: 'invoices.php' }
        ],
        contracts: [
            { id: 'CON-101', name: 'Master Services Agreement 2026', meta: 'Contract • Acme Corp', type: 'Contract', link: 'contracts.php' },
            { id: 'CON-102', name: 'Enterprise SLA Agreement', meta: 'Contract • Fortis Group', type: 'Contract', link: 'contracts.php' }
        ],
        proposals: [
            { id: 'PRP-101', name: 'Enterprise AI Platform Proposal', meta: 'Proposal • Synapse AI', type: 'Proposal', link: 'proposals.php' },
            { id: 'PRP-102', name: 'Cloud Architecture Consulting', meta: 'Proposal • CloudShift', type: 'Proposal', link: 'proposals.php' }
        ],
        estimates: [
            { id: 'EST-2026-004', name: 'EST-2026-004', meta: 'Estimate • Custom CRM Integration ($15,000)', type: 'Estimate', link: 'estimate-requests.php' },
            { id: 'EST-2026-003', name: 'EST-2026-003', meta: 'Estimate • Security Audit & Compliance ($9,500)', type: 'Estimate', link: 'estimate-requests.php' }
        ],
        documents: [
            { id: 'DOC-101', name: 'Enterprise SLA Terms 2026.pdf', meta: 'Document • Acme Corp', type: 'Document', link: 'documents.php' },
            { id: 'DOC-102', name: 'Technical Architecture Deck.pptx', meta: 'Document • Synapse AI', type: 'Document', link: 'documents.php' }
        ]
    };

    const SVG_ICONS = {
        Lead: `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="17" y1="11" x2="23" y2="11"/></svg>`,
        Contact: `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`,
        Company: `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M3 7v14M21 7v14M6 11h4M6 15h4M14 11h4M14 15h4M9 3h6v4H9z"/></svg>`,
        Deal: `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>`,
        Project: `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>`,
        Task: `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>`,
        Invoice: `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`,
        Contract: `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>`,
        Proposal: `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>`,
        Estimate: `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="16" y2="14"/></svg>`,
        Document: `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>`
    };

    let highlightedIndex = -1;

    function getStorageData(key, fallbackArray) {
        try {
            const raw = localStorage.getItem(key);
            if (raw) {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed) && parsed.length > 0) return parsed;
            }
        } catch (e) {
            console.warn(`GlobalSearch: Error reading ${key}:`, e);
        }
        return fallbackArray;
    }

    function getAllSearchableRecords() {
        const records = [];

        // 1. Leads
        const leads = getStorageData(STORAGE_KEYS.leads, defaultData.leads);
        leads.forEach(l => {
            const name = l.name || l.title || 'Untitled Lead';
            const company = l.company || l.companyName || '';
            records.push({
                title: name,
                type: 'Lead',
                meta: company ? `Lead • ${company}` : 'Lead Record',
                link: `leads.php?search=${encodeURIComponent(name)}`,
                searchStr: `${name} ${company} ${l.email || ''} ${l.id || ''}`.toLowerCase()
            });
        });

        // 2. Contacts
        const contacts = getStorageData(STORAGE_KEYS.contacts, defaultData.contacts);
        contacts.forEach(c => {
            const name = c.name || 'Untitled Contact';
            const meta = c.meta || (c.company ? `Contact • ${c.company}` : 'Contact Record');
            records.push({
                title: name,
                type: 'Contact',
                meta: meta,
                link: `contacts.php?search=${encodeURIComponent(name)}`,
                searchStr: `${name} ${c.company || ''} ${c.email || ''} ${c.phone || ''}`.toLowerCase()
            });
        });

        // 3. Companies
        const companies = getStorageData(STORAGE_KEYS.companies, defaultData.companies);
        companies.forEach(com => {
            const name = com.name || com.company || 'Untitled Company';
            const meta = com.meta || (com.industry ? `Company • ${com.industry}` : 'Company Record');
            records.push({
                title: name,
                type: 'Company',
                meta: meta,
                link: `companies.php?search=${encodeURIComponent(name)}`,
                searchStr: `${name} ${com.industry || ''} ${com.owner || ''}`.toLowerCase()
            });
        });

        // 4. Deals
        const deals = getStorageData(STORAGE_KEYS.deals, defaultData.deals);
        deals.forEach(d => {
            const name = d.name || d.title || 'Untitled Deal';
            const meta = d.meta || (d.company ? `Deal • ${d.company}` : 'Deal Record');
            records.push({
                title: name,
                type: 'Deal',
                meta: meta,
                link: `pipeline.php?search=${encodeURIComponent(name)}`,
                searchStr: `${name} ${d.company || ''} ${d.stage || ''}`.toLowerCase()
            });
        });

        // 5. Projects
        const projects = getStorageData(STORAGE_KEYS.projects, defaultData.projects);
        projects.forEach(p => {
            const name = p.name || p.title || 'Untitled Project';
            const meta = p.meta || (p.company ? `Project • ${p.company}` : 'Project Record');
            records.push({
                title: name,
                type: 'Project',
                meta: meta,
                link: `projects.php?search=${encodeURIComponent(name)}`,
                searchStr: `${name} ${p.company || ''} ${p.client || ''}`.toLowerCase()
            });
        });

        // 6. Tasks
        const tasks = getStorageData(STORAGE_KEYS.tasks, defaultData.tasks);
        tasks.forEach(t => {
            const name = t.name || t.title || 'Untitled Task';
            const meta = t.meta || (t.company || t.contact ? `Task • ${t.company || t.contact}` : 'Task Record');
            records.push({
                title: name,
                type: 'Task',
                meta: meta,
                link: `tasks.php?search=${encodeURIComponent(name)}`,
                searchStr: `${name} ${t.company || ''} ${t.contact || ''} ${t.assignee || ''}`.toLowerCase()
            });
        });

        // 7. Invoices
        const invoices = getStorageData(STORAGE_KEYS.invoices, defaultData.invoices);
        invoices.forEach(inv => {
            const name = inv.name || inv.number || inv.title || 'Untitled Invoice';
            const meta = inv.meta || (inv.company ? `Invoice • ${inv.company}` : 'Invoice Record');
            records.push({
                title: name,
                type: 'Invoice',
                meta: meta,
                link: `invoices.php?search=${encodeURIComponent(name)}`,
                searchStr: `${name} ${inv.company || ''} ${inv.client || ''} ${inv.status || ''}`.toLowerCase()
            });
        });

        // 8. Contracts
        const contracts = getStorageData(STORAGE_KEYS.contracts, defaultData.contracts);
        contracts.forEach(con => {
            const name = con.name || con.title || 'Untitled Contract';
            const meta = con.meta || (con.company ? `Contract • ${con.company}` : 'Contract Record');
            records.push({
                title: name,
                type: 'Contract',
                meta: meta,
                link: `contracts.php?search=${encodeURIComponent(name)}`,
                searchStr: `${name} ${con.company || ''} ${con.type || ''}`.toLowerCase()
            });
        });

        // 9. Proposals
        const proposals = getStorageData(STORAGE_KEYS.proposals, defaultData.proposals);
        proposals.forEach(prp => {
            const name = prp.name || prp.title || 'Untitled Proposal';
            const meta = prp.meta || (prp.company ? `Proposal • ${prp.company}` : 'Proposal Record');
            records.push({
                title: name,
                type: 'Proposal',
                meta: meta,
                link: `proposals.php?search=${encodeURIComponent(name)}`,
                searchStr: `${name} ${prp.company || ''} ${prp.status || ''}`.toLowerCase()
            });
        });

        // 10. Estimates
        const estimates = getStorageData(STORAGE_KEYS.estimates, defaultData.estimates);
        estimates.forEach(est => {
            const name = est.name || est.title || est.number || 'Untitled Estimate';
            const meta = est.meta || (est.company ? `Estimate • ${est.company}` : 'Estimate Record');
            records.push({
                title: name,
                type: 'Estimate',
                meta: meta,
                link: `estimate-requests.php?search=${encodeURIComponent(name)}`,
                searchStr: `${name} ${est.company || ''} ${est.client || ''}`.toLowerCase()
            });
        });

        // 11. Documents
        const documents = getStorageData(STORAGE_KEYS.documents, defaultData.documents);
        documents.forEach(doc => {
            const name = doc.name || doc.title || 'Untitled Document';
            const meta = doc.meta || (doc.company ? `Document • ${doc.company}` : 'Document Record');
            records.push({
                title: name,
                type: 'Document',
                meta: meta,
                link: `documents.php?search=${encodeURIComponent(name)}`,
                searchStr: `${name} ${doc.company || ''} ${doc.category || ''}`.toLowerCase()
            });
        });

        return records;
    }

    function initGlobalSearch() {
        const searchInput = document.getElementById('globalSearchInput');
        const clearBtn = document.getElementById('globalSearchClearBtn');
        const popover = document.getElementById('globalSearchResultsPopover');

        if (!searchInput || !popover) return;

        function closePopover() {
            popover.classList.remove('show');
            popover.innerHTML = '';
            highlightedIndex = -1;
        }

        function handleSearchQuery(query) {
            query = query.trim().toLowerCase();

            if (!query) {
                closePopover();
                if (clearBtn) clearBtn.style.display = 'none';
                return;
            }

            if (clearBtn) clearBtn.style.display = 'flex';

            const allRecords = getAllSearchableRecords();
            const matches = allRecords.filter(r => r.searchStr.includes(query));

            if (matches.length === 0) {
                popover.innerHTML = `
                    <div class="global-search-empty">
                        <div class="global-search-empty-title">No results found</div>
                        <div class="global-search-empty-desc">
                            Try searching for a lead, contact, company, deal, project, task, or document.
                        </div>
                    </div>
                `;
                popover.classList.add('show');
                highlightedIndex = -1;
                return;
            }

            const maxResults = 7;
            const displayResults = matches.slice(0, maxResults);
            const hasMore = matches.length > maxResults;

            let html = '<div class="global-search-list" id="globalSearchList">';

            displayResults.forEach((res, idx) => {
                const icon = SVG_ICONS[res.type] || SVG_ICONS.Document;
                html += `
                    <a href="${res.link}" class="global-search-item" data-index="${idx}">
                        <div class="global-search-item-icon">${icon}</div>
                        <div class="global-search-item-content">
                            <div class="global-search-item-title">${res.title}</div>
                            <div class="global-search-item-meta">
                                <span class="global-search-badge">${res.type}</span>
                                <span>${res.meta}</span>
                            </div>
                        </div>
                    </a>
                `;
            });

            html += '</div>';

            if (hasMore) {
                html += `
                    <div class="global-search-footer" onclick="window.location.href='leads.php?search=${encodeURIComponent(query)}'">
                        View all ${matches.length} results
                    </div>
                `;
            }

            popover.innerHTML = html;
            popover.classList.add('show');
            highlightedIndex = -1;
        }

        // Input listener
        searchInput.addEventListener('input', function () {
            handleSearchQuery(this.value);
        });

        // Focus listener
        searchInput.addEventListener('focus', function () {
            if (this.value.trim()) {
                handleSearchQuery(this.value);
            }
        });

        // Clear button
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                searchInput.focus();
                closePopover();
                clearBtn.style.display = 'none';
            });
        }

        // Keyboard navigation (ArrowUp, ArrowDown, Enter, ESC)
        searchInput.addEventListener('keydown', function (e) {
            if (!popover.classList.contains('show')) return;

            const items = popover.querySelectorAll('.global-search-item');
            if (items.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightedIndex = (highlightedIndex + 1) % items.length;
                updateHighlight(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightedIndex = (highlightedIndex - 1 + items.length) % items.length;
                updateHighlight(items);
            } else if (e.key === 'Enter') {
                if (highlightedIndex >= 0 && items[highlightedIndex]) {
                    e.preventDefault();
                    items[highlightedIndex].click();
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closePopover();
            }
        });

        function updateHighlight(items) {
            items.forEach((item, idx) => {
                if (idx === highlightedIndex) {
                    item.classList.add('highlighted');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('highlighted');
                }
            });
        }

        // Click outside listener
        document.addEventListener('click', function (e) {
            if (!popover.contains(e.target) && !searchInput.contains(e.target) && (!clearBtn || !clearBtn.contains(e.target))) {
                closePopover();
            }
        });

        // Keydown Escape global listener
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && popover.classList.contains('show')) {
                closePopover();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGlobalSearch);
    } else {
        initGlobalSearch();
    }
})();

