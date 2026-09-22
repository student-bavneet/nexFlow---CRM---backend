/**
 * NexFlow CRM - Universal CRM Onboarding Wizard JavaScript Controller
 * Database-backed onboarding wizard controller
 */
(function() {
    'use strict';

    let INDUSTRIES_DATA = [];
    let MODULES_DATA = [];
    let INDUSTRY_RECOMMENDATIONS = {};

    let state = {
        currentStep: 1,
        selectedIndustry: '',
        customIndustryName: '',
        selectedModules: [],
        customModules: [],
        terminology: {
            leads: 'Leads',
            pipeline: 'Sales Pipeline',
            contacts: 'Contacts',
            companies: 'Companies',
            deals: 'Deals'
        },
        pipelineStages: [],
        customFields: [],
        activeFieldObject: 'Leads',
        activeCustTab: 'tab-terms'
    };

    async function initOnboarding() {
        try {
            await loadCatalog();
            renderIndustryGrid();
            renderModuleGrid();
            renderCustomFields();
            renderPipelineStages();
            renderLiveMenuPreview();
            bindEvents();
            updateStepUI();
        } catch (error) {
            console.error(error);
            showAlertModal({
                title: 'Database Connection Required',
                message: error.message || 'Could not load onboarding configuration from the database.',
                type: 'error'
            });
        }
    }

    async function loadCatalog() {
        const response = await fetch('api/onboarding.php?action=bootstrap', {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        });

        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Unable to load onboarding configuration.');
        }

        if (result.data.installed) {
            window.location.href = 'admin-login.php';
            return;
        }

        INDUSTRIES_DATA = Array.isArray(result.data.industries) ? result.data.industries : [];
        MODULES_DATA = Array.isArray(result.data.modules) ? result.data.modules : [];
        INDUSTRY_RECOMMENDATIONS = result.data.recommendations || {};

        if (!state.selectedIndustry && INDUSTRIES_DATA.length) {
            const first = INDUSTRIES_DATA.find(i => i.key === 'software') || INDUSTRIES_DATA[0];
            state.selectedIndustry = first.key;
            applyIndustryRecommendation(first.key, false);
        }
    }

    function applyIndustryRecommendation(key, persistUi = true) {
        const rec = INDUSTRY_RECOMMENDATIONS[key];
        if (!rec) return;

        state.selectedModules = [...(rec.modules || [])];
        state.terminology = { ...(rec.terms || {}) };
        state.pipelineStages = (rec.stages || []).map((stage, i) => ({
            id: i + 1,
            name: typeof stage === 'string' ? stage : stage.name,
            pct: typeof stage === 'string' ? ((i + 1) * 20) : Number(stage.pct || ((i + 1) * 20))
        }));

        setVal('obTermLeads', state.terminology.leads);
        setVal('obTermPipeline', state.terminology.pipeline);
        setVal('obTermContacts', state.terminology.contacts);
        setVal('obTermCompanies', state.terminology.companies);
        setVal('obTermDeals', state.terminology.deals);

        const titleEl = document.getElementById('obRecEngineTitle');
        const subEl = document.getElementById('obRecEngineSub');
        if (titleEl) titleEl.textContent = rec.title || '';
        if (subEl) subEl.textContent = rec.sub || '';

        if (persistUi) {
            renderModuleGrid();
            renderPipelineStages();
            renderLiveMenuPreview();
        }
    }

    function saveState() {
        // Onboarding is persisted to MySQL only when the installer is completed.
        // No business data is stored in localStorage anymore.
    }

    function renderIndustryGrid() {
        const container = document.getElementById('obIndustryGrid');
        if (!container) return;

        container.innerHTML = INDUSTRIES_DATA.map(item => {
            const isSelected = state.selectedIndustry === item.key;
            return `
                <div class="ob-industry-card ${isSelected ? 'selected' : ''}" onclick="window.obApp.selectIndustry('${item.key}')">
                    <div class="ob-industry-card-top">
                        <div class="ob-industry-icon" style="background:#F1F5F9;">${item.icon}</div>
                        <div class="ob-industry-check">${isSelected ? '✓' : ''}</div>
                    </div>
                    <h3 class="ob-industry-name">${escapeHtml(item.name)}</h3>
                    <p class="ob-industry-desc">${escapeHtml(item.description)}</p>
                </div>
            `;
        }).join('');
    }

    function selectIndustry(key) {
        state.selectedIndustry = key;
        state.customIndustryName = '';

        const otherWrap = document.getElementById('obOtherIndustryWrap');
        if (otherWrap) otherWrap.style.display = key === 'other' ? 'block' : 'none';

        applyIndustryRecommendation(key);
        renderIndustryGrid();
        renderLiveMenuPreview();
    }

    function renderModuleGrid() {
        const container = document.getElementById('obModuleGrid');
        if (!container) return;

        let html = MODULES_DATA.map(item => {
            const isSelected = state.selectedModules.includes(item.key);
            return `
                <div class="ob-module-card ${isSelected ? 'selected' : ''}" onclick="window.obApp.toggleModule('${item.key}')">
                    <div class="ob-module-top">
                        <div class="ob-module-icon">${item.icon}</div>
                        <input type="checkbox" ${isSelected ? 'checked' : ''} onclick="event.stopPropagation(); window.obApp.toggleModule('${item.key}')">
                    </div>
                    <h4 class="ob-module-name">${escapeHtml(item.name)}</h4>
                    <p class="ob-module-desc">${escapeHtml(item.description)}</p>
                </div>
            `;
        }).join('');

        state.customModules.forEach(c => {
            html += `
                <div class="ob-module-card selected" style="border-color:#7C3AED; background:#F5F3FF; position:relative;">
                    <div class="ob-module-top">
                        <div class="ob-module-icon" style="background:#DDD6FE; color:#6D28D9;">${escapeHtml(c.icon || '✨')}</div>
                        <span style="background:#7C3AED; color:#FFF; font-size:10px; padding:2px 7px; border-radius:12px; font-weight:700;">Custom</span>
                    </div>
                    <h4 class="ob-module-name" style="margin-top:6px;">${escapeHtml(c.name)}</h4>
                    <p class="ob-module-desc">${escapeHtml(c.desc || 'Custom module created for your business')}</p>
                </div>
            `;
        });

        html += `
            <div class="ob-module-card" style="border:2px dashed #CBD5E1; background:#F8FAFC; justify-content:center; align-items:center; text-align:center; cursor:pointer;" onclick="window.obApp.openCustomModuleModal()">
                <div style="font-size:24px; color:var(--primary);">+</div>
                <div style="font-weight:700; font-size:13px; color:var(--primary);">Create Custom Module</div>
            </div>
        `;

        container.innerHTML = html;
    }

    function toggleModule(key) {
        const idx = state.selectedModules.indexOf(key);
        if (idx >= 0) {
            state.selectedModules.splice(idx, 1);
        } else {
            state.selectedModules.push(key);
        }
        saveState();
        renderModuleGrid();
    }

    let selectedCustomIcon = '✨';

    function openCustomModuleModal() {
        const modal = document.getElementById('obCustomModuleModal');
        const errDiv = document.getElementById('obCustomModuleError');
        const nameInput = document.getElementById('obCustomModuleName');
        const descInput = document.getElementById('obCustomModuleDesc');

        if (errDiv) errDiv.style.display = 'none';
        if (nameInput) nameInput.value = '';
        if (descInput) descInput.value = '';
        selectedCustomIcon = '✨';

        document.querySelectorAll('.ob-icon-option').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-icon') === '✨');
        });

        if (modal) {
            modal.style.display = 'flex';
            if (nameInput) setTimeout(() => nameInput.focus(), 100);
        }
    }

    function closeCustomModuleModal() {
        const modal = document.getElementById('obCustomModuleModal');
        if (modal) modal.style.display = 'none';
    }

    function selectCustomModuleIcon(btnEl, iconStr) {
        selectedCustomIcon = iconStr;
        document.querySelectorAll('.ob-icon-option').forEach(btn => btn.classList.remove('active'));
        if (btnEl) btnEl.classList.add('active');
    }

    function submitCustomModule() {
        const nameInput = document.getElementById('obCustomModuleName');
        const descInput = document.getElementById('obCustomModuleDesc');
        const errDiv = document.getElementById('obCustomModuleError');

        const name = nameInput ? nameInput.value.trim() : '';
        const desc = descInput ? descInput.value.trim() : '';

        if (!name) {
            if (errDiv) {
                errDiv.style.display = 'block';
                errDiv.textContent = 'Please enter a module name.';
            }
            if (nameInput) nameInput.focus();
            return;
        }

        const customKey = `custom_${Date.now()}`;
        state.customModules.push({
            key: customKey,
            name: name,
            desc: desc || 'Custom module created for your business',
            icon: selectedCustomIcon || '✨'
        });

        if (!state.selectedModules.includes(customKey)) {
            state.selectedModules.push(customKey);
        }

        saveState();
        renderModuleGrid();
        closeCustomModuleModal();
    }

    function applyRecommendedSetup() {
        const rec = INDUSTRY_RECOMMENDATIONS[state.selectedIndustry] || INDUSTRY_RECOMMENDATIONS.realestate;
        state.selectedModules = [...rec.modules];
        saveState();
        renderModuleGrid();
        renderLiveMenuPreview();
        showAlertModal({ title: "Configuration Applied", message: "Recommended module configuration applied successfully!", type: "success" });
    }

    function switchCustTab(tabId) {
        state.activeCustTab = tabId;
        document.querySelectorAll('.ob-cust-tab').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-tab') === tabId);
        });
        document.querySelectorAll('.ob-cust-pane').forEach(pane => {
            pane.classList.toggle('active', pane.id === tabId);
        });
    }

    function setFieldObject(objName) {
        state.activeFieldObject = objName;
        document.querySelectorAll('#obFieldObjectChips .filter-chip').forEach(btn => {
            btn.classList.toggle('active', btn.textContent.trim() === objName);
        });
        renderCustomFields();
    }

    function renderCustomFields() {
        const list = document.getElementById('obCustomFieldsList');
        if (!list) return;

        const filtered = state.customFields.filter(f => f.object === state.activeFieldObject);
        if (filtered.length === 0) {
            list.innerHTML = `<div style="font-size:12px; color:var(--text-muted); text-align:center; padding:16px;">No custom fields created for ${state.activeFieldObject} yet. Click "+ Add Custom Field" above.</div>`;
            return;
        }

        list.innerHTML = filtered.map(f => `
            <div style="display:flex; justify-content:space-between; align-items:center; background:#FFF; border:1px solid #E2E8F0; padding:10px 14px; border-radius:6px; margin-bottom:8px;">
                <div>
                    <strong style="font-size:13px; color:var(--text-heading);">${escapeHtml(f.name)}</strong>
                    <span style="font-size:11px; color:var(--text-muted); margin-left:8px;">(${f.type})</span>
                </div>
                <div>
                    <span class="status-badge" style="font-size:10px;">${f.req ? 'Required' : 'Optional'}</span>
                </div>
            </div>
        `).join('');
    }

    function openAddFieldModal() {
        showPromptModal({
            title: "Add Custom Field",
            label: `Enter field name for ${state.activeFieldObject}:`,
            placeholder: "e.g. Secondary Contact Phone",
            confirmText: "Add Field",
            onConfirm: function(name) {
                if (name && name.trim()) {
                    state.customFields.push({
                        id: Date.now(),
                        object: state.activeFieldObject,
                        name: name.trim(),
                        type: 'Short Text',
                        req: false
                    });
                    saveState();
                    renderCustomFields();
                }
            }
        });
    }

    function renderPipelineStages() {
        const container = document.getElementById('obPipelineStageList');
        if (!container) return;

        container.innerHTML = state.pipelineStages.map((s, idx) => `
            <div style="display:flex; align-items:center; justify-content:space-between; background:#F8FAFC; border:1px solid #E2E8F0; padding:10px 14px; border-radius:6px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-weight:700; font-size:12px; color:var(--primary);">${idx + 1}.</span>
                    <input type="text" class="input-control" value="${escapeHtml(s.name)}" onchange="window.obApp.updateStageName(${s.id}, this.value)" style="width:220px; height:32px; font-size:13px;">
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="font-size:12px; color:var(--text-muted);">${s.pct}% Win Rate</span>
                    <button type="button" class="btn btn-ghost btn-xs" style="color:#DC2626;" onclick="window.obApp.deletePipelineStage(${s.id})">Delete</button>
                </div>
            </div>
        `).join('');
    }

    function updateStageName(id, newName) {
        const st = state.pipelineStages.find(s => s.id === id);
        if (st) {
            st.name = newName;
            saveState();
        }
    }

    function addPipelineStage() {
        showPromptModal({
            title: "Add Pipeline Stage",
            label: "Enter new pipeline stage name:",
            placeholder: "e.g. Demonstration Complete",
            confirmText: "Add Stage",
            onConfirm: function(name) {
                if (name && name.trim()) {
                    state.pipelineStages.push({
                        id: Date.now(),
                        name: name.trim(),
                        pct: 50
                    });
                    saveState();
                    renderPipelineStages();
                }
            }
        });
    }

    function deletePipelineStage(id) {
        state.pipelineStages = state.pipelineStages.filter(s => s.id !== id);
        saveState();
        renderPipelineStages();
    }

    function addCustomTag() {
        const input = document.getElementById('obNewTagInput');
        if (!input || !input.value.trim()) return;

        const container = document.getElementById('obTagsContainer');
        if (container) {
            const span = document.createElement('span');
            span.className = 'status-badge';
            span.style.cssText = 'background:#EFF6FF; color:#2563EB;';
            span.textContent = input.value.trim();
            container.appendChild(span);
        }
        input.value = '';
    }

    function renderLiveMenuPreview() {
        const container = document.getElementById('obLiveMenuPreview');
        if (!container) return;

        const terms = {
            leads: getVal('obTermLeads') || state.terminology.leads,
            pipeline: getVal('obTermPipeline') || state.terminology.pipeline,
            contacts: getVal('obTermContacts') || state.terminology.contacts,
            companies: getVal('obTermCompanies') || state.terminology.companies,
            deals: getVal('obTermDeals') || state.terminology.deals
        };

        const coreLinks = [
            { key: 'leads', label: terms.leads, icon: '🎯' },
            { key: 'pipeline', label: terms.pipeline, icon: '📈' },
            { key: 'contacts', label: terms.contacts, icon: '👤' },
            { key: 'companies', label: terms.companies, icon: '🏢' },
            { key: 'deals', label: terms.deals, icon: '💼' }
        ];

        let html = `
            <div style="font-size:11px; font-weight:700; color:#94A3B8; text-transform:uppercase; margin-bottom:8px;">Main Menu</div>
            <div style="display:flex; flex-direction:column; gap:4px;">
        `;

        coreLinks.forEach(link => {
            if (state.selectedModules.includes(link.key)) {
                html += `
                    <div style="display:flex; align-items:center; gap:8px; padding:6px 10px; border-radius:6px; background:#334155; font-size:12.5px; font-weight:500;">
                        <span>${link.icon}</span>
                        <span>${escapeHtml(link.label)}</span>
                    </div>
                `;
            }
        });

        state.customModules.forEach(c => {
            html += `
                <div style="display:flex; align-items:center; gap:8px; padding:6px 10px; border-radius:6px; background:#4C1D95; color:#DDD6FE; font-size:12.5px; font-weight:500;">
                    <span>✨</span>
                    <span>${escapeHtml(c.name)}</span>
                </div>
            `;
        });

        html += `</div>`;
        container.innerHTML = html;
    }

    function updateStepUI() {
        document.querySelectorAll('.ob-step-pane').forEach((pane, idx) => {
            pane.classList.toggle('active', idx + 1 === state.currentStep);
        });

        document.querySelectorAll('.ob-step-item').forEach((item, idx) => {
            const stepNum = idx + 1;
            item.classList.toggle('active', stepNum === state.currentStep);
            item.classList.toggle('completed', stepNum < state.currentStep);
        });

        const btnBack = document.getElementById('btnObBack');
        const btnNext = document.getElementById('btnObNext');

        if (btnBack) btnBack.disabled = state.currentStep === 1;
        if (btnNext) {
            if (state.currentStep === 5) {
                btnNext.textContent = 'Continue →';
                btnNext.style.backgroundColor = 'var(--primary, #2563EB)';
            } else if (state.currentStep === 6) {
                btnNext.textContent = '🚀 Enter My CRM';
                btnNext.style.backgroundColor = '#10B981';
            } else {
                btnNext.textContent = 'Continue →';
                btnNext.style.backgroundColor = 'var(--primary, #2563EB)';
            }
        }

        if (state.currentStep === 4) {
            renderSetupPane();
        } else if (state.currentStep === 5) {
            renderPreviewPane();
        } else if (state.currentStep === 6) {
            renderReadyPane();
        }
    }

    function renderSetupPane() {
        const indItem = INDUSTRIES_DATA.find(i => i.key === state.selectedIndustry);
        const indName = state.selectedIndustry === 'other' ? (getVal('obOtherIndustryInput') || 'Custom Industry') : (indItem ? indItem.name : 'Standard');

        setTxt('obSetupBizName', getVal('obBizName') || 'Apex Solutions Inc.');
        setTxt('obSetupIndustry', indName);

        const listContainer = document.getElementById('obSetupModulesList');
        if (listContainer) {
            const selectedLabels = state.selectedModules.map(mKey => {
                const found = MODULES_DATA.find(m => m.key === mKey);
                return found ? found.name : mKey;
            });

            listContainer.innerHTML = selectedLabels.map(name => `
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="color:#10B981; font-weight:700;">✓</span>
                    <span>${escapeHtml(name)}</span>
                </div>
            `).join('');
        }
    }

    function renderPreviewPane() {
        const indItem = INDUSTRIES_DATA.find(i => i.key === state.selectedIndustry);
        const indName = state.selectedIndustry === 'other' ? (getVal('obOtherIndustryInput') || 'Custom Industry') : (indItem ? indItem.name : 'Standard');

        setTxt('obPrevBizName', getVal('obBizName') || 'Apex Solutions Inc.');
        setTxt('obPrevIndustry', indName);
        setTxt('obPrevLocation', (getVal('obBizCity') || 'San Francisco') + ', ' + (getVal('obBizState') || 'CA'));
        setTxt('obPrevCurrency', getVal('obBizCurrency') || 'USD ($)');
        setTxt('obPrevModCount', `${state.selectedModules.length} Active Modules`);
    }

    function renderReadyPane() {
        const indItem = INDUSTRIES_DATA.find(i => i.key === state.selectedIndustry);
        const indName = state.selectedIndustry === 'other' ? (getVal('obOtherIndustryInput') || 'Custom Industry') : (indItem ? indItem.name : 'Standard');

        setTxt('obFinalBizName', getVal('obBizName') || 'Apex Solutions Inc.');
        setTxt('obFinalIndustry', indName);
        setTxt('obFinalModules', `${state.selectedModules.length} Active Modules`);
    }

    function nextStep() {
        if (state.currentStep === 1) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            const bizName = getVal('obBizName').trim();
            if (!bizName) {
                alert('Please enter your business name.');
                document.getElementById('obBizName')?.focus();
                return;
            }

            const bizEmail = getVal('obBizEmail').trim();
            if (!bizEmail) {
                alert('Please enter your business email.');
                document.getElementById('obBizEmail')?.focus();
                return;
            }
            if (!emailRegex.test(bizEmail)) {
                alert('Please enter a valid business email address.');
                document.getElementById('obBizEmail')?.focus();
                return;
            }

            const country = getVal('obBizCountry').trim();
            if (!country) {
                alert('Please select your country.');
                document.getElementById('obBizCountry')?.focus();
                return;
            }

            const city = getVal('obBizCity').trim();
            if (!city) {
                alert('Please enter your city.');
                document.getElementById('obBizCity')?.focus();
                return;
            }

            const currency = getVal('obBizCurrency').trim();
            if (!currency) {
                alert('Please select your currency.');
                document.getElementById('obBizCurrency')?.focus();
                return;
            }

            const timezone = getVal('obBizTimezone').trim();
            if (!timezone) {
                alert('Please select your time zone.');
                document.getElementById('obBizTimezone')?.focus();
                return;
            }

            const website = getVal('obBizWebsite').trim();
            if (website) {
                const urlPattern = /^(https?:\/\/)?([\w\-]+\.)+[\w\-]+(\/.*)?$/i;
                if (!urlPattern.test(website)) {
                    alert('Please enter a valid website URL.');
                    document.getElementById('obBizWebsite')?.focus();
                    return;
                }
            }

            const logoInput = document.getElementById('obBizLogo');
            if (logoInput && logoInput.files && logoInput.files[0]) {
                const logoFile = logoInput.files[0];
                const logoName = logoFile.name || '';
                const logoExt = logoName.split('.').pop().toLowerCase();
                const allowedLogoExts = ['jpg', 'jpeg', 'png', 'svg'];
                if (!allowedLogoExts.includes(logoExt)) {
                    alert('Please upload a valid logo file. Allowed formats: JPG, JPEG, PNG, SVG.');
                    logoInput.value = '';
                    const logoPreview = document.getElementById('obLogoPreviewBox');
                    if (logoPreview) logoPreview.innerHTML = 'LOGO';
                    return;
                }
                if (logoFile.size > 2 * 1024 * 1024) {
                    alert('Logo size must be 2 MB or less.');
                    logoInput.value = '';
                    const logoPreview = document.getElementById('obLogoPreviewBox');
                    if (logoPreview) logoPreview.innerHTML = 'LOGO';
                    return;
                }
            }

            const adminName = getVal('obAdminName').trim();
            if (!adminName) {
                alert('Please enter administrator name.');
                document.getElementById('obAdminName')?.focus();
                return;
            }

            const adminEmail = getVal('obAdminEmail').trim();
            if (!adminEmail) {
                alert('Please enter administrator login email.');
                document.getElementById('obAdminEmail')?.focus();
                return;
            }
            if (!emailRegex.test(adminEmail)) {
                alert('Please enter a valid administrator login email address.');
                document.getElementById('obAdminEmail')?.focus();
                return;
            }

            const adminPassword = getVal('obAdminPassword');
            if (!adminPassword) {
                alert('Please enter administrator password.');
                document.getElementById('obAdminPassword')?.focus();
                return;
            }
            if (adminPassword.length < 8) {
                alert('Password must be at least 8 characters.');
                document.getElementById('obAdminPassword')?.focus();
                return;
            }

            const adminPasswordConfirm = getVal('obAdminPasswordConfirm');
            if (!adminPasswordConfirm) {
                alert('Please confirm administrator password.');
                document.getElementById('obAdminPasswordConfirm')?.focus();
                return;
            }
            if (adminPassword !== adminPasswordConfirm) {
                alert('Passwords do not match.');
                document.getElementById('obAdminPasswordConfirm')?.focus();
                return;
            }
        }

        if (state.currentStep === 2) {
            if (!state.selectedIndustry) {
                alert('Please select your industry.');
                return;
            }
            if (state.selectedIndustry === 'other' && !getVal('obOtherIndustryInput').trim()) {
                alert('Please enter your custom industry name.');
                document.getElementById('obOtherIndustryInput')?.focus();
                return;
            }
        }

        if (state.currentStep === 3) {
            if (!state.selectedModules || state.selectedModules.length === 0) {
                alert('Please select at least one module.');
                return;
            }
        }

        if (state.currentStep < 6) {
            state.currentStep++;
            updateStepUI();
        } else {
            finishOnboarding();
        }
    }

    function prevStep() {
        if (state.currentStep > 1) {
            state.currentStep--;
            saveState();
            updateStepUI();
        }
    }

    async function finishOnboarding() {
        const business = {
            name: getVal('obBizName').trim(),
            email: getVal('obBizEmail').trim(),
            phone: getVal('obBizPhone').trim(),
            website: getVal('obBizWebsite').trim(),
            country: getVal('obBizCountry').trim(),
            state: getVal('obBizState').trim(),
            city: getVal('obBizCity').trim(),
            zip: getVal('obBizZip').trim(),
            address: getVal('obBizAddress').trim(),
            currency: getVal('obBizCurrency').trim(),
            timezone: getVal('obBizTimezone').trim()
        };

        const admin = {
            name: getVal('obAdminName').trim(),
            email: getVal('obAdminEmail').trim(),
            password: getVal('obAdminPassword'),
            password_confirm: getVal('obAdminPasswordConfirm')
        };

        const btnNext = document.getElementById('btnObNext');
        if (btnNext) {
            btnNext.disabled = true;
            btnNext.textContent = 'Setting up...';
        }

        const formData = new FormData();
        formData.append('action', 'finish');
        formData.append('payload', JSON.stringify({
            business,
            admin,
            selectedIndustry: state.selectedIndustry,
            customIndustryName: getVal('obOtherIndustryInput').trim(),
            selectedModules: state.selectedModules,
            customModules: state.customModules,
            pipelineStages: state.pipelineStages,
            customFields: state.customFields,
            terminology: {
                leads: getVal('obTermLeads') || state.terminology.leads || 'Leads',
                pipeline: getVal('obTermPipeline') || state.terminology.pipeline || 'Sales Pipeline',
                contacts: getVal('obTermContacts') || state.terminology.contacts || 'Contacts',
                companies: getVal('obTermCompanies') || state.terminology.companies || 'Companies',
                deals: getVal('obTermDeals') || state.terminology.deals || 'Deals'
            }
        }));

        const logoInput = document.getElementById('obBizLogo');
        if (logoInput && logoInput.files && logoInput.files[0]) {
            formData.append('logo', logoInput.files[0]);
        }

        try {
            const response = await fetch('api/onboarding.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Setup could not be completed.');
            }

            window.location.href = result.data.redirect || 'index.php';
        } catch (error) {
            if (btnNext) {
                btnNext.disabled = false;
                btnNext.textContent = '🚀 Enter My CRM';
            }
            alert(error.message || 'An unexpected error occurred while saving your CRM setup.');
        }
    }

    function bindEvents() {
        const btnBack = document.getElementById('btnObBack');
        const btnNext = document.getElementById('btnObNext');

        if (btnBack) btnBack.addEventListener('click', prevStep);
        if (btnNext) btnNext.addEventListener('click', nextStep);

        const logoInput = document.getElementById('obBizLogo');
        const logoPreview = document.getElementById('obLogoPreviewBox');
        if (logoInput && logoPreview) {
            logoInput.addEventListener('change', () => {
                const file = logoInput.files && logoInput.files[0];
                if (!file) {
                    if (logoPreview) logoPreview.innerHTML = 'LOGO';
                    return;
                }
                const fileName = file.name || '';
                const ext = fileName.split('.').pop().toLowerCase();
                const allowed = ['jpg', 'jpeg', 'png', 'svg'];
                if (!allowed.includes(ext)) {
                    alert('Please upload a valid logo file. Allowed formats: JPG, JPEG, PNG, SVG.');
                    logoInput.value = '';
                    if (logoPreview) logoPreview.innerHTML = 'LOGO';
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    alert('Logo size must be 2 MB or less.');
                    logoInput.value = '';
                    if (logoPreview) logoPreview.innerHTML = 'LOGO';
                    return;
                }
                const reader = new FileReader();
                reader.onload = e => {
                    if (logoPreview) {
                        logoPreview.innerHTML = `<img src="${e.target.result}" alt="Logo preview" style="max-width:100%;max-height:100%;object-fit:contain;border-radius:6px;">`;
                    }
                };
                reader.readAsDataURL(file);
            });
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeCustomModuleModal();
        });
    }

    function getVal(id) {
        const el = document.getElementById(id);
        return el ? el.value : '';
    }
    function setVal(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val || '';
    }
    function setTxt(id, txt) {
        const el = document.getElementById(id);
        if (el) el.textContent = txt || '';
    }
    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    window.obApp = {
        selectIndustry,
        toggleModule,
        openCustomModuleModal,
        closeCustomModuleModal,
        selectCustomModuleIcon,
        submitCustomModule,
        applyRecommendedSetup,
        switchCustTab,
        setFieldObject,
        openAddFieldModal,
        addPipelineStage,
        updateStageName,
        deletePipelineStage,
        addCustomTag,
        nextStep,
        prevStep,
        finishOnboarding
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOnboarding);
    } else {
        initOnboarding();
    }
})();

