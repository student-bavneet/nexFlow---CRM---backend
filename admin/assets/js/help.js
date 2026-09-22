/**
 * NexFlow CRM - Help & Support JavaScript Controller
 * Idempotent initialization, live hero search with keyboard navigation,
 * article drawer with history navigation, onboarding checklist with localStorage,
 * Contact Support drawer with FileReader attachment preview, My Demo Requests drawer,
 * System Info copy to clipboard, Video tutorial modal, and FAQ accordion.
 */

(function () {
    const STORAGE_KEY_CHECKLIST = "NexFlow_checklist_state";
    const STORAGE_KEY_REQUESTS = "NexFlow_demo_requests";

    let articlesData = [];
    let categoriesData = [];
    let faqsData = [];
    let troubleshootingData = [];

    let searchResults = [];
    let selectedSearchIdx = -1;
    let articleHistory = [];
    let currentArticleId = null;

    function initHelpPage(forceReinit) {
        const root = document.querySelector(".help-page");
        if (!root) return;

        if (window._helpPageInitialized && !forceReinit) return;
        window._helpPageInitialized = true;

        if (window.HELP_MOCK_DATA) {
            articlesData = window.HELP_MOCK_DATA.articles || [];
            categoriesData = window.HELP_MOCK_DATA.categories || [];
            faqsData = window.HELP_MOCK_DATA.faqs || [];
            troubleshootingData = window.HELP_MOCK_DATA.troubleshooting || [];
        }

        setupSearch();
        setupKeyboardShortcuts();
        setupChecklist();
        setupSystemInfo();
        setupDrawers();
    }

    window.initHelpPage = initHelpPage;

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () { initHelpPage(); });
    } else {
        initHelpPage();
    }

    // Hero Live Search with Keyboard Navigation
    function setupSearch() {
        const input = document.getElementById("helpSearchInput");
        const dropdown = document.getElementById("helpSearchResultsDropdown");

        if (!input || !dropdown) return;

        input.addEventListener("input", function () {
            const q = this.value.toLowerCase().trim();
            if (!q) {
                dropdown.classList.remove("show");
                dropdown.innerHTML = "";
                searchResults = [];
                selectedSearchIdx = -1;
                return;
            }

            searchResults = articlesData.filter(art => {
                const title = art.title.toLowerCase();
                const category = art.category.toLowerCase();
                const intro = art.intro.toLowerCase();
                const keywords = (art.keywords || []).join(" ").toLowerCase();
                return title.includes(q) || category.includes(q) || intro.includes(q) || keywords.includes(q);
            });

            renderSearchResults(dropdown);
        });

        input.addEventListener("keydown", function (e) {
            if (!searchResults.length) return;

            if (e.key === "ArrowDown") {
                e.preventDefault();
                selectedSearchIdx = Math.min(selectedSearchIdx + 1, searchResults.length - 1);
                updateSelectedResult(dropdown);
            } else if (e.key === "ArrowUp") {
                e.preventDefault();
                selectedSearchIdx = Math.max(selectedSearchIdx - 1, 0);
                updateSelectedResult(dropdown);
            } else if (e.key === "Enter" && selectedSearchIdx >= 0) {
                e.preventDefault();
                const art = searchResults[selectedSearchIdx];
                if (art) {
                    openArticle(art.id);
                    dropdown.classList.remove("show");
                    input.blur();
                }
            }
        });
    }

    function renderSearchResults(container) {
        if (!searchResults.length) {
            container.innerHTML = `<div style="padding:14px 16px;color:var(--text-muted);font-size:13px;text-align:center;">No matching articles found. Try searching for "leads", "pipeline", or "softphone".</div>`;
            container.classList.add("show");
            return;
        }

        selectedSearchIdx = -1;
        container.innerHTML = searchResults.map((art, idx) => `
            <div class="help-search-result-item" data-index="${idx}" onclick="window.helpApp.openArticle('${art.id}')">
                <div class="help-search-result-icon">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div class="help-search-result-info">
                    <div class="help-search-result-title">
                        <span>${art.title}</span>
                        <span class="help-search-result-badge">${art.category}</span>
                    </div>
                    <div class="help-search-result-desc">${art.intro}</div>
                </div>
            </div>
        `).join("");

        container.classList.add("show");
    }

    function updateSelectedResult(container) {
        const items = container.querySelectorAll(".help-search-result-item");
        items.forEach((item, idx) => {
            if (idx === selectedSearchIdx) {
                item.classList.add("selected");
                item.scrollIntoView({ block: "nearest" });
            } else {
                item.classList.remove("selected");
            }
        });
    }

    // Keyboard Shortcuts
    function setupKeyboardShortcuts() {
        document.addEventListener("keydown", function (e) {
            if (e.key === "/" && document.activeElement.tagName !== "INPUT" && document.activeElement.tagName !== "TEXTAREA") {
                const searchInput = document.getElementById("helpSearchInput");
                if (searchInput) {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            }

            if (e.key === "Escape") {
                const searchDropdown = document.getElementById("helpSearchResultsDropdown");
                if (searchDropdown && searchDropdown.classList.contains("show")) {
                    searchDropdown.classList.remove("show");
                }

                closeAllDrawersAndModals();
            }
        });
    }

    // Article Drawer & Navigation History
    function openArticle(articleId, isBackNavigation) {
        const article = articlesData.find(a => a.id === articleId);
        if (!article) return;

        if (!isBackNavigation) {
            if (currentArticleId && currentArticleId !== articleId) {
                articleHistory.push(currentArticleId);
            } else {
                articleHistory = [];
            }
        }
        currentArticleId = articleId;

        const drawer = document.getElementById("helpArticleDrawer");
        const panel = document.getElementById("helpArticleContentPanel");
        if (!drawer || !panel) return;

        const backBtnHtml = articleHistory.length > 0
            ? `<button type="button" class="btn btn-secondary btn-xs" onclick="window.helpApp.navigateArticleBack()">
                 <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg> Back
               </button>`
            : `<span style="font-size:12px;color:var(--text-secondary);font-weight:600;">${article.category}</span>`;

        panel.innerHTML = `
            <div class="help-article-header">
                <div style="display:flex;align-items:center;gap:10px;">
                    ${backBtnHtml}
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <span style="font-size:12px;color:var(--text-muted);">${article.readTime} • Updated ${article.updated}</span>
                    <button type="button" class="modal-close-btn" onclick="document.getElementById('helpArticleDrawer').classList.remove('show')">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>
            <div class="help-article-body">
                <h1 style="font-size:22px;font-weight:700;color:var(--text-heading);margin-bottom:8px;">${article.title}</h1>
                <p style="font-size:14px;color:var(--text-secondary);margin-bottom:16px;line-height:1.5;">${article.intro}</p>

                ${article.toc && article.toc.length ? `
                    <div class="help-toc-box">
                        <div class="help-toc-title">Table of Contents</div>
                        <ul class="help-toc-list">
                            ${article.toc.map(item => `<li><a href="#sec-${encodeURIComponent(item)}">${item}</a></li>`).join("")}
                        </ul>
                    </div>
                ` : ''}

                ${article.sections.map(sec => `
                    <div class="help-article-section" id="sec-${encodeURIComponent(sec.heading)}">
                        <h2 class="help-article-section-h2">${sec.heading}</h2>
                        <div class="help-article-section-p">${sec.content}</div>
                    </div>
                `).join("")}

                ${article.tip ? `<div class="help-box-tip"><strong>💡 Pro Tip:</strong> ${article.tip}</div>` : ''}
                ${article.note ? `<div class="help-box-note"><strong>📌 Important Note:</strong> ${article.note}</div>` : ''}

                <div class="help-feedback-box">
                    <span style="font-size:13px;font-weight:600;color:var(--text-heading);">Was this article helpful?</span>
                    <div style="display:flex;gap:8px;">
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.helpApp.rateArticle('${article.id}', true, this)">👍 Yes</button>
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.helpApp.rateArticle('${article.id}', false, this)">👎 No</button>
                    </div>
                </div>

                ${renderRelatedArticles(article.related)}
            </div>
        `;

        drawer.classList.add("show");
    }

    function navigateArticleBack() {
        if (articleHistory.length > 0) {
            const previousId = articleHistory.pop();
            openArticle(previousId, true);
        }
    }

    function renderRelatedArticles(relatedIds) {
        if (!relatedIds || !relatedIds.length) return '';

        const relatedArts = articlesData.filter(a => relatedIds.includes(a.id));
        if (!relatedArts.length) return '';

        return `
            <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border-divider);">
                <h3 style="font-size:14px;font-weight:600;color:var(--text-heading);margin-bottom:12px;">Related Articles</h3>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    ${relatedArts.map(ra => `
                        <div class="help-article-row" onclick="window.helpApp.openArticle('${ra.id}')">
                            <div>
                                <div class="help-article-title">${ra.title}</div>
                                <div class="help-article-meta">${ra.category} • ${ra.readTime}</div>
                            </div>
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted);"><polyline points="9 18 15 12 9 6"/></svg>
                        </div>
                    `).join("")}
                </div>
            </div>
        `;
    }

    function rateArticle(id, isHelpful, btnEl) {
        const parent = btnEl.parentElement;
        parent.innerHTML = `<span style="font-size:12px;color:#10B981;font-weight:600;">✓ Thanks for your feedback!</span>`;
    }

    // Onboarding Checklist
    function setupChecklist() {
        const saved = localStorage.getItem(STORAGE_KEY_CHECKLIST);
        let checklistState = {};
        if (saved) {
            try { checklistState = JSON.parse(saved); } catch (e) {}
        }

        const items = document.querySelectorAll(".help-checklist-item");
        items.forEach(item => {
            const id = item.getAttribute("data-id");
            const checkbox = item.querySelector(".help-checklist-checkbox");

            if (checklistState[id]) {
                item.classList.add("done");
                if (checkbox) checkbox.checked = true;
            }

            if (checkbox) {
                checkbox.addEventListener("change", function () {
                    checklistState[id] = this.checked;
                    if (this.checked) {
                        item.classList.add("done");
                    } else {
                        item.classList.remove("done");
                    }
                    localStorage.setItem(STORAGE_KEY_CHECKLIST, JSON.stringify(checklistState));
                    updateChecklistProgress();
                });
            }
        });

        updateChecklistProgress();
    }

    function updateChecklistProgress() {
        const items = document.querySelectorAll(".help-checklist-item");
        const total = items.length;
        if (!total) return;

        let completed = 0;
        items.forEach(item => {
            if (item.classList.contains("done")) completed++;
        });

        const pct = Math.round((completed / total) * 100);
        const fill = document.getElementById("helpChecklistProgressFill");
        const text = document.getElementById("helpChecklistProgressText");

        if (fill) fill.style.width = pct + "%";
        if (text) text.textContent = `${completed} of ${total} completed (${pct}%)`;
    }

    // System Information & Clipboard
    function setupSystemInfo() {
        const ua = navigator.userAgent;
        let browserName = "Browser";
        if (ua.includes("Chrome")) browserName = "Chrome";
        else if (ua.includes("Firefox")) browserName = "Firefox";
        else if (ua.includes("Safari")) browserName = "Safari";
        else if (ua.includes("Edg")) browserName = "Edge";

        const width = window.innerWidth;
        const height = window.innerHeight;
        const online = navigator.onLine ? "Online" : "Offline";
        const lsAvailable = typeof localStorage !== "undefined" ? "Available" : "Disabled";

        setSys("sysBrowser", browserName);
        setSys("sysViewport", `${width} × ${height} px`);
        setSys("sysPage", "Help & Support");
        setSys("sysURL", window.location.href);
        setSys("sysOnline", online);
        setSys("sysStorage", lsAvailable);
    }

    function setSys(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function copySystemInfo() {
        const sysText = `NexFlow CRM Debug Info:
Browser: ${document.getElementById("sysBrowser")?.textContent || ''}
Viewport: ${document.getElementById("sysViewport")?.textContent || ''}
Page: ${document.getElementById("sysPage")?.textContent || ''}
URL: ${document.getElementById("sysURL")?.textContent || ''}
Network: ${document.getElementById("sysOnline")?.textContent || ''}
LocalStorage: ${document.getElementById("sysStorage")?.textContent || ''}`;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(sysText).then(() => {
                showToast("System information copied to clipboard.", "success");
            }).catch(() => {
                showToast("Failed to copy info to clipboard.", "warning");
            });
        } else {
            showToast("System info copied locally.", "info");
        }
    }

    // Drawers & Modals Setup
    function setupDrawers() {
        // Support Form Attachment FileReader Preview
        const fileInput = document.getElementById("supportAttachmentInput");
        const filePreview = document.getElementById("supportAttachmentPreview");

        if (fileInput) {
            fileInput.addEventListener("change", function () {
                const file = this.files[0];
                if (!file) return;

                if (file.size > 5 * 1024 * 1024) {
                    showToast("Attachment file size must be under 5MB.", "warning");
                    this.value = "";
                    return;
                }

                if (filePreview) {
                    filePreview.style.display = "block";
                    filePreview.innerHTML = `
                        <div class="settings-banner settings-banner-info" style="margin-bottom:0;padding:8px 12px;font-size:12px;">
                            📁 <strong>Attached:</strong> ${file.name} (${(file.size / 1024).toFixed(1)} KB)
                        </div>
                    `;
                }
            });
        }

        // Support Form Submission
        const supportForm = document.getElementById("supportRequestForm");
        if (supportForm) {
            supportForm.addEventListener("submit", function (e) {
                e.preventDefault();
                const name = document.getElementById("suppNameInput")?.value || "Olivia Martin";
                const email = document.getElementById("suppEmailInput")?.value || "olivia.martin@NexFlow.io";
                const topic = document.getElementById("suppTopicSelect")?.value || "General Question";
                const priority = document.getElementById("suppPrioritySelect")?.value || "Normal";
                const subject = document.getElementById("suppSubjectInput")?.value.trim();
                const desc = document.getElementById("suppDescInput")?.value.trim();

                if (!subject || !desc) {
                    showToast("Please fill in Subject and Description.", "warning");
                    return;
                }

                const newReq = {
                    id: "REQ-" + Math.floor(100000 + Math.random() * 900000),
                    name, email, topic, priority, subject, desc,
                    date: new Date().toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" }),
                    status: "Draft Demo"
                };

                const saved = localStorage.getItem(STORAGE_KEY_REQUESTS);
                let requests = [];
                if (saved) {
                    try { requests = JSON.parse(saved); } catch (e) {}
                }
                requests.unshift(newReq);
                localStorage.setItem(STORAGE_KEY_REQUESTS, JSON.stringify(requests));

                document.getElementById("helpSupportDrawer")?.classList.remove("show");
                supportForm.reset();
                if (filePreview) filePreview.style.display = "none";

                showToast(`Support request ${newReq.id} saved in this browser as a frontend demo.`, "success");
            });
        }
    }

    function openContactSupport(prefillTopic) {
        const drawer = document.getElementById("helpSupportDrawer");
        const topicSelect = document.getElementById("suppTopicSelect");
        if (topicSelect && prefillTopic) {
            topicSelect.value = prefillTopic;
        }

        const envInput = document.getElementById("suppEnvDetailsInput");
        if (envInput) {
            envInput.value = `Browser: ${navigator.userAgent.substring(0, 60)}... | Screen: ${window.innerWidth}x${window.innerHeight}`;
        }

        if (drawer) drawer.classList.add("show");
    }

    function openMyDemoRequests() {
        const drawer = document.getElementById("helpRequestsDrawer");
        const tbody = document.getElementById("helpRequestsTbody");
        if (!drawer || !tbody) return;

        const saved = localStorage.getItem(STORAGE_KEY_REQUESTS);
        let requests = [];
        if (saved) {
            try { requests = JSON.parse(saved); } catch (e) {}
        }

        if (!requests.length) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">No demo support requests saved in browser. Click "Contact Support" to create one.</td></tr>`;
        } else {
            tbody.innerHTML = requests.map((r, idx) => `
                <tr>
                    <td style="font-weight:600;color:var(--primary);">${r.id}</td>
                    <td style="font-weight:600;color:var(--text-heading);">${r.subject}</td>
                    <td><span class="status-badge status-new">${r.topic}</span></td>
                    <td><span class="status-badge ${r.priority === 'High' || r.priority === 'Urgent' ? 'status-lost' : 'status-proposal'}">${r.priority}</span></td>
                    <td>
                        <button type="button" class="btn btn-ghost btn-xs" style="color:#DC2626;" onclick="window.helpApp.deleteDemoRequest(${idx})">Delete</button>
                    </td>
                </tr>
            `).join("");
        }

        drawer.classList.add("show");
    }

    function deleteDemoRequest(idx) {
        const saved = localStorage.getItem(STORAGE_KEY_REQUESTS);
        let requests = [];
        if (saved) {
            try { requests = JSON.parse(saved); } catch (e) {}
        }

        if (requests[idx]) {
            requests.splice(idx, 1);
            localStorage.setItem(STORAGE_KEY_REQUESTS, JSON.stringify(requests));
            openMyDemoRequests();
            showToast("Demo request copy removed from local storage.", "info");
        }
    }

    function openTutorialModal(tutId) {
        const tut = (window.HELP_MOCK_DATA?.tutorials || []).find(t => t.id === tutId);
        const modal = document.getElementById("helpTutorialModal");
        const titleEl = document.getElementById("tutModalTitle");
        const descEl = document.getElementById("tutModalDesc");

        if (titleEl && tut) titleEl.textContent = tut.title;
        if (descEl && tut) descEl.textContent = tut.desc;
        if (modal) modal.classList.add("show");
    }

    function filterByCategory(catName) {
        const input = document.getElementById("helpSearchInput");
        if (input) {
            input.value = catName;
            input.dispatchEvent(new Event("input"));
            input.focus();
        }
    }

    function openTroubleshooting(tbId) {
        const item = troubleshootingData.find(t => t.id === tbId);
        if (!item) return;

        showAlertModal({
            title: `Troubleshooting: ${item.title}`,
            message: `Problem:\n${item.problem}\n\nRecommended Steps:\n${item.steps.map((s, i) => `${i + 1}. ${s}`).join("\n")}`,
            type: "info",
            buttonText: "Got it"
        });
    }

    function toggleFAQ(headerEl) {
        const parent = headerEl.parentElement;
        if (parent) {
            parent.classList.toggle("open");
        }
    }

    function closeAllDrawersAndModals() {
        document.querySelectorAll(".help-article-drawer, .help-support-drawer, .help-requests-drawer, .help-modal-overlay").forEach(el => {
            el.classList.remove("show");
        });
    }

    // Toast Notification Helper
    function showToast(message, type = "info") {
        let container = document.querySelector(".settings-toast-container");
        if (!container) {
            container = document.createElement("div");
            container.className = "settings-toast-container";
            document.body.appendChild(container);
        }

        const toast = document.createElement("div");
        toast.className = `settings-toast ${type}`;
        toast.innerHTML = `<span>${message}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = "0";
            toast.style.transition = "opacity 0.25s ease";
            setTimeout(() => toast.remove(), 250);
        }, 3000);
    }

    // Public API Window Binding
    window.helpApp = {
        openArticle,
        navigateArticleBack,
        rateArticle,
        openContactSupport,
        openMyDemoRequests,
        deleteDemoRequest,
        openTutorialModal,
        filterByCategory,
        openTroubleshooting,
        toggleFAQ,
        copySystemInfo,
        showToast
    };
})();

