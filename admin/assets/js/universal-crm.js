/**
 * NexFlow CRM - Universal & Multi-Industry Frontend Customization Engine
 * Manages Industry Presets, Terminology Overrides, and Dynamic Field Adapters.
 */
(function() {
    'use strict';

    const STORAGE_KEY = 'NexFlow_universal_crm_config';

    const INDUSTRY_PRESETS = {
        standard: {
            name: "Standard B2B Sales",
            terminology: {
                leads: "Leads",
                pipeline: "Sales Pipeline",
                contacts: "Contacts",
                companies: "Companies",
                deals: "Deals"
            }
        },
        realestate: {
            name: "Real Estate & Property",
            terminology: {
                leads: "Buyers",
                pipeline: "Property Pipeline",
                contacts: "Clients",
                companies: "Brokerages",
                deals: "Properties"
            }
        },
        healthcare: {
            name: "Healthcare & Medical",
            terminology: {
                leads: "Patients",
                pipeline: "Intake Pipeline",
                contacts: "Specialists",
                companies: "Practices",
                deals: "Consultations"
            }
        },
        legal: {
            name: "Legal & Professional Services",
            terminology: {
                leads: "Inquiries",
                pipeline: "Case Workflow",
                contacts: "Clients",
                companies: "Law Firms",
                deals: "Cases / Matters"
            }
        },
        saas: {
            name: "SaaS & B2B Tech",
            terminology: {
                leads: "MQLs",
                pipeline: "Subscription Pipeline",
                contacts: "Users",
                companies: "Accounts",
                deals: "Subscriptions"
            }
        },
        education: {
            name: "Education & Academy",
            terminology: {
                leads: "Applicants",
                pipeline: "Admissions Pipeline",
                contacts: "Students",
                companies: "Academies",
                deals: "Programs"
            }
        }
    };

    function getDefaultConfig() {
        return {
            preset: 'standard',
            terminology: { ...INDUSTRY_PRESETS.standard.terminology },
            customFields: []
        };
    }

    function getConfig() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return getDefaultConfig();
            const parsed = JSON.parse(raw);
            return {
                preset: parsed.preset || 'standard',
                terminology: { ...INDUSTRY_PRESETS.standard.terminology, ...(parsed.terminology || {}) },
                customFields: Array.isArray(parsed.customFields) ? parsed.customFields : []
            };
        } catch (e) {
            return getDefaultConfig();
        }
    }

    function saveConfig(config) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(config));
        apply();
        window.dispatchEvent(new CustomEvent('universalCrmChanged', { detail: config }));
    }

    function apply() {
        const config = getConfig();
        const terms = config.terminology;

        // 1. Update Sidebar Nav Items
        const sidebarNavMap = {
            'leads': terms.leads,
            'pipeline': terms.pipeline,
            'contacts': terms.contacts,
            'companies': terms.companies
        };

        Object.keys(sidebarNavMap).forEach(key => {
            const label = sidebarNavMap[key];
            if (!label) return;

            // Find sidebar links
            const navLink = document.querySelector(`.sidebar-nav-item[href="${key}.php"] .sidebar-nav-label`);
            if (navLink) {
                navLink.textContent = label;
            }
        });

        // 2. Update Page Header Title if on relevant page
        const currentPageKey = window.NexFlowCurrentPageKey || '';
        if (currentPageKey && sidebarNavMap[currentPageKey]) {
            const pageTitleEl = document.querySelector('.team-header-title, .leads-header-title, .contacts-header-title, .companies-header-title, .pipeline-header-title');
            if (pageTitleEl && sidebarNavMap[currentPageKey]) {
                pageTitleEl.textContent = sidebarNavMap[currentPageKey];
            }
        }
    }

    window.universalCrm = {
        presets: INDUSTRY_PRESETS,
        getConfig: getConfig,
        saveConfig: saveConfig,
        apply: apply,
        getTerminology: function() {
            return getConfig().terminology;
        },
        getCustomFields: function(moduleKey) {
            const config = getConfig();
            return config.customFields.filter(f => (f.object || '').toLowerCase() === (moduleKey || '').toLowerCase());
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', apply);
    } else {
        apply();
    }
})();

