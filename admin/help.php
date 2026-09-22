<?php
$page_title = "Help & Support";
$current_page = "help";
$page_script = "help.js";

// Include central help mock data
require_once __DIR__ . '/data/help-mock-data.php';

include __DIR__ . '/includes/header.php';
requirePermission('help');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<main class="main-content">
    <div class="page-container help-page">

        <!-- Page Header -->
        <div class="help-header">
            <div class="help-header-left">
                <h1 class="help-header-title">Help &amp; Support</h1>
                <p class="help-header-subtitle">Find answers, learn NexFlow and get assistance.</p>
            </div>
            <div class="help-header-right">
                <button type="button" class="btn btn-secondary btn-md" onclick="window.helpApp.openMyDemoRequests()">My Demo Requests</button>
                <button type="button" class="btn btn-primary btn-md" onclick="window.helpApp.openContactSupport()">Contact Support</button>
            </div>
        </div>

        <!-- Hero Search Section -->
        <div class="help-hero-search">
            <h2 class="help-hero-title">How can we help?</h2>
            <p class="help-hero-subtitle">Search articles, features and common questions across NexFlow CRM.</p>
            
            <div class="help-search-container">
                <div class="help-search-input-wrapper">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="help-search-icon"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="help-search-input" id="helpSearchInput" placeholder="Search articles, features and common questions..." autocomplete="off">
                    <span class="help-search-kbd-hint">Press <code>/</code> to search</span>
                </div>
                <div class="help-search-results-dropdown" id="helpSearchResultsDropdown">
                    <!-- Dynamic search results populated by JS -->
                </div>
            </div>
        </div>

        <!-- Quick Actions (4 Cards) -->
        <div class="help-quick-actions">
            <div class="help-action-card" onclick="window.helpApp.openArticle('art-001')">
                <div class="help-action-icon">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <div>
                    <div class="help-action-title">Getting Started</div>
                    <div class="help-action-desc">Set up workspace and learn basics</div>
                </div>
            </div>

            <div class="help-action-card" onclick="window.helpApp.openArticle('art-002')">
                <div class="help-action-icon">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                </div>
                <div>
                    <div class="help-action-title">Manage Leads</div>
                    <div class="help-action-desc">Import, organize and follow up</div>
                </div>
            </div>

            <div class="help-action-card" onclick="window.helpApp.openArticle('art-003')">
                <div class="help-action-icon">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 3v12"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 01-9 9"/></svg>
                </div>
                <div>
                    <div class="help-action-title">Sales Pipeline</div>
                    <div class="help-action-desc">Track deals and kanban stages</div>
                </div>
            </div>

            <div class="help-action-card" onclick="window.helpApp.openContactSupport()">
                <div class="help-action-icon" style="background-color:#F0FDF4;color:#10B981;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                </div>
                <div>
                    <div class="help-action-title">Contact Support</div>
                    <div class="help-action-desc">Create a demo support request</div>
                </div>
            </div>
        </div>

        <!-- Knowledge Base Categories (12 Grid Cards) -->
        <div style="margin-bottom:32px;">
            <div class="help-section-header">
                <h2 class="help-section-title">Knowledge Base Categories</h2>
                <p class="help-section-subtitle">Browse documentation grouped by feature and topic area.</p>
            </div>

            <div class="help-category-grid">
                <?php foreach ($help_categories as $cat): ?>
                <div class="help-category-card" onclick="window.helpApp.filterByCategory('<?php echo htmlspecialchars($cat['name']); ?>')">
                    <div>
                        <div class="help-category-top">
                            <div class="help-category-icon">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                            </div>
                            <span class="status-badge status-new" style="font-size:11px;"><?php echo $cat['count']; ?> articles</span>
                        </div>
                        <h3 class="help-category-name"><?php echo htmlspecialchars($cat['name']); ?></h3>
                        <p class="help-category-desc"><?php echo htmlspecialchars($cat['desc']); ?></p>
                    </div>
                    <div class="help-category-footer">
                        <span>View Articles</span>
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Two-Column Section: Popular Articles & Onboarding Checklist -->
        <div class="help-two-col">
            <!-- Left: Popular Articles -->
            <div>
                <div class="help-section-header">
                    <h2 class="help-section-title">Popular Articles</h2>
                    <p class="help-section-subtitle">Frequently consulted guides and documentation.</p>
                </div>

                <div class="help-article-list">
                    <?php foreach ($help_articles as $art): ?>
                    <div class="help-article-row" onclick="window.helpApp.openArticle('<?php echo $art['id']; ?>')">
                        <div>
                            <div class="help-article-title"><?php echo htmlspecialchars($art['title']); ?></div>
                            <div class="help-article-meta">
                                <span class="status-badge status-qualified" style="font-size:10px;padding:1px 6px;"><?php echo htmlspecialchars($art['category']); ?></span>
                                <span>• <?php echo htmlspecialchars($art['readTime']); ?></span>
                                <?php if ($art['popular']): ?>
                                <span class="status-badge status-proposal" style="font-size:10px;padding:1px 6px;">Popular</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--text-muted);"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right: Onboarding Checklist -->
            <div>
                <div class="help-section-header">
                    <h2 class="help-section-title">Getting Started Checklist</h2>
                    <p class="help-section-subtitle">Interactive onboarding guide for your CRM workspace.</p>
                </div>

                <div class="help-checklist-card">
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <span style="font-size:13px;font-weight:600;color:var(--text-heading);">Overall Progress</span>
                        <span style="font-size:12px;color:var(--text-secondary);" id="helpChecklistProgressText">0 of 7 completed (0%)</span>
                    </div>
                    <div class="help-checklist-progress-bar">
                        <div class="help-checklist-progress-fill" id="helpChecklistProgressFill" style="width:0%;"></div>
                    </div>

                    <div style="display:flex;flex-direction:column;">
                        <?php foreach ($help_checklist_defaults as $chk): ?>
                        <div class="help-checklist-item" data-id="<?php echo $chk['id']; ?>">
                            <input type="checkbox" class="help-checklist-checkbox">
                            <div style="flex:1;">
                                <div class="help-checklist-title"><?php echo htmlspecialchars($chk['title']); ?></div>
                                <div class="help-checklist-desc"><?php echo htmlspecialchars($chk['desc']); ?></div>
                            </div>
                            <a href="<?php echo $chk['url']; ?>" class="btn btn-ghost btn-xs" style="color:var(--primary);margin-left:auto;">Open</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Video Tutorials (6 Cards Grid) -->
        <div style="margin-bottom:32px;">
            <div class="help-section-header">
                <h2 class="help-section-title">Video Tutorials</h2>
                <p class="help-section-subtitle">Watch walkthrough video guides for key feature workflows.</p>
            </div>

            <div class="help-tutorials-grid">
                <?php foreach ($help_tutorials as $tut): ?>
                <div class="help-tutorial-card" onclick="window.helpApp.openTutorialModal('<?php echo $tut['id']; ?>')">
                    <div class="help-tutorial-thumb">
                        <div class="help-tutorial-play-btn">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        </div>
                        <span class="help-tutorial-duration"><?php echo htmlspecialchars($tut['duration']); ?></span>
                    </div>
                    <div class="help-tutorial-body">
                        <span class="status-badge status-new" style="font-size:10px;margin-bottom:6px;"><?php echo htmlspecialchars($tut['category']); ?></span>
                        <h3 class="help-tutorial-title"><?php echo htmlspecialchars($tut['title']); ?></h3>
                        <p class="help-tutorial-desc"><?php echo htmlspecialchars($tut['desc']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Frequently Asked Questions (Accordion) -->
        <div style="margin-bottom:32px;">
            <div class="help-section-header">
                <h2 class="help-section-title">Frequently Asked Questions</h2>
                <p class="help-section-subtitle">Clear answers explaining the frontend demonstration architecture.</p>
            </div>

            <div class="help-faq-container">
                <?php foreach ($help_faqs as $faq): ?>
                <div class="help-faq-item">
                    <button type="button" class="help-faq-header" onclick="window.helpApp.toggleFAQ(this)">
                        <span><?php echo htmlspecialchars($faq['q']); ?></span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="help-faq-icon"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="help-faq-body">
                        <?php echo $faq['a']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Support Options Cards (4 Cards) -->
        <div style="margin-bottom:32px;">
            <div class="help-section-header">
                <h2 class="help-section-title">Support Options</h2>
                <p class="help-section-subtitle">Select how you would like to connect with technical assistance.</p>
            </div>

            <div class="help-support-grid">
                <div class="help-support-card">
                    <div>
                        <span class="status-badge status-won" style="font-size:10px;margin-bottom:8px;">Self-Service</span>
                        <h3 class="help-support-title">Help Articles</h3>
                        <p class="help-support-desc">Search knowledge base documentation and step-by-step guides.</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('helpSearchInput').focus()">Search KB</button>
                </div>

                <div class="help-support-card">
                    <div>
                        <span class="status-badge status-new" style="font-size:10px;margin-bottom:8px;">Demo Request</span>
                        <h3 class="help-support-title">Contact Support</h3>
                        <p class="help-support-desc">Create a local demo support ticket saved to browser storage.</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="window.helpApp.openContactSupport()">Contact Support</button>
                </div>

                <div class="help-support-card">
                    <div>
                        <span class="status-badge status-proposal" style="font-size:10px;margin-bottom:8px;">Future Integration</span>
                        <h3 class="help-support-title">Live Chat</h3>
                        <p class="help-support-desc">Real-time messaging with support agents requires backend server support.</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" disabled>Chat Offline</button>
                </div>

                <div class="help-support-card">
                    <div>
                        <span class="status-badge status-proposal" style="font-size:10px;margin-bottom:8px;">Future Integration</span>
                        <h3 class="help-support-title">System Status</h3>
                        <p class="help-support-desc">Live infrastructure monitoring and server uptime checks.</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" disabled>Not Connected</button>
                </div>
            </div>
        </div>

        <!-- Troubleshooting Cards (5 Cards) -->
        <div style="margin-bottom:32px;">
            <div class="help-section-header">
                <h2 class="help-section-title">Troubleshooting Guides</h2>
                <p class="help-section-subtitle">Local development and browser debugging assistance.</p>
            </div>

            <div class="help-troubleshooting-grid">
                <?php foreach ($help_troubleshooting as $tb): ?>
                <div class="help-tb-card" onclick="window.helpApp.openTroubleshooting('<?php echo $tb['id']; ?>')">
                    <h3 class="help-tb-title"><?php echo htmlspecialchars($tb['title']); ?></h3>
                    <p class="help-tb-desc"><?php echo htmlspecialchars($tb['problem']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- System Information Panel -->
        <div class="help-system-info-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div>
                    <h3 style="font-size:16px;font-weight:700;color:var(--text-heading);margin-bottom:2px;">System Environment Information</h3>
                    <p style="font-size:12px;color:var(--text-secondary);">Useful environment parameters generated locally for debugging.</p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.helpApp.copySystemInfo()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                    Copy System Info
                </button>
            </div>

            <div class="help-sys-grid">
                <div class="help-sys-item">
                    <div class="help-sys-label">Browser Engine</div>
                    <div class="help-sys-value" id="sysBrowser">Detecting...</div>
                </div>
                <div class="help-sys-item">
                    <div class="help-sys-label">Viewport Resolution</div>
                    <div class="help-sys-value" id="sysViewport">Detecting...</div>
                </div>
                <div class="help-sys-item">
                    <div class="help-sys-label">Current Page</div>
                    <div class="help-sys-value" id="sysPage">Help &amp; Support</div>
                </div>
                <div class="help-sys-item">
                    <div class="help-sys-label">Network Status</div>
                    <div class="help-sys-value" id="sysOnline">Online</div>
                </div>
            </div>

            <!-- Frontend Status Panel -->
            <div class="help-status-panel">
                <span style="font-weight:700;color:var(--text-heading);">Local Frontend Status:</span>
                <div class="help-status-item"><span class="help-status-dot active"></span> CRM Frontend — Available</div>
                <div class="help-status-item"><span class="help-status-dot active"></span> Local PHP Server — Active</div>
                <div class="help-status-item"><span class="help-status-dot inactive"></span> Database — Not Connected</div>
                <div class="help-status-item"><span class="help-status-dot inactive"></span> Telephony Provider — Not Connected</div>
            </div>
        </div>

    </div>
</main>

<!-- Article Drawer (620–700px) -->
<div class="help-article-drawer" id="helpArticleDrawer">
    <div class="help-article-panel" id="helpArticleContentPanel">
        <!-- Content rendered dynamically via JS -->
    </div>
</div>

<!-- Support Drawer (~500px) -->
<div class="help-support-drawer" id="helpSupportDrawer">
    <div class="help-support-panel">
        <div class="help-article-header">
            <h3 style="font-size:17px;font-weight:600;color:var(--text-heading);margin:0;">Contact Support (Demo Request)</h3>
            <button type="button" class="modal-close-btn" onclick="document.getElementById('helpSupportDrawer').classList.remove('show')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="supportRequestForm" style="display:flex;flex-direction:column;flex:1;">
            <div class="help-article-body">
                <div class="settings-banner settings-banner-info">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>Real ticket submission, email delivery and agent replies require backend support integration. Requests created here are saved locally as frontend demo data.</div>
                </div>

                <div class="settings-form-grid" style="margin-bottom:16px;">
                    <div class="settings-field-group">
                        <label class="settings-label" for="suppNameInput">Your Name *</label>
                        <input type="text" class="input-control" id="suppNameInput" value="Olivia Martin" required>
                    </div>
                    <div class="settings-field-group">
                        <label class="settings-label" for="suppEmailInput">Your Email *</label>
                        <input type="email" class="input-control" id="suppEmailInput" value="olivia.martin@NexFlow.io" required>
                    </div>
                </div>

                <div class="settings-form-grid" style="margin-bottom:16px;">
                    <div class="settings-field-group">
                        <label class="settings-label" for="suppTopicSelect">Topic *</label>
                        <select class="input-control" id="suppTopicSelect">
                            <option value="General Question">General Question</option>
                            <option value="Feature Help">Feature Help</option>
                            <option value="UI Problem">UI Problem</option>
                            <option value="Data Import">Data Import</option>
                            <option value="Softphone">Softphone</option>
                            <option value="Inbox">Inbox</option>
                            <option value="Reports">Reports</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="settings-field-group">
                        <label class="settings-label" for="suppPrioritySelect">Priority *</label>
                        <select class="input-control" id="suppPrioritySelect">
                            <option value="Low">Low</option>
                            <option value="Normal" selected>Normal</option>
                            <option value="High">High</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div class="settings-field-group" style="margin-bottom:16px;">
                    <label class="settings-label" for="suppSubjectInput">Subject *</label>
                    <input type="text" class="input-control" id="suppSubjectInput" placeholder="Brief summary of your question or issue" required>
                </div>

                <div class="settings-field-group" style="margin-bottom:16px;">
                    <label class="settings-label" for="suppDescInput">Description *</label>
                    <textarea class="input-control" id="suppDescInput" rows="4" style="height:auto;padding:8px 12px;" placeholder="Provide details about what you were trying to do..." required></textarea>
                </div>

                <div class="settings-field-group" style="margin-bottom:16px;">
                    <label class="settings-label" for="suppEnvDetailsInput">Browser &amp; Environment Details</label>
                    <input type="text" class="input-control" id="suppEnvDetailsInput" readonly style="background-color:#F1F5F9;font-size:12px;">
                </div>

                <div class="settings-field-group" style="margin-bottom:16px;">
                    <label class="settings-label">Attachment (Optional local preview)</label>
                    <input type="file" id="supportAttachmentInput" accept="image/*,.pdf,.doc,.docx" class="input-control" style="padding-top:4px;">
                    <div id="supportAttachmentPreview" style="margin-top:6px;display:none;"></div>
                </div>
            </div>

            <div class="settings-drawer-footer">
                <button type="button" class="btn btn-secondary btn-md" onclick="document.getElementById('helpSupportDrawer').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-md">Save Demo Request</button>
            </div>
        </form>
    </div>
</div>

<!-- My Demo Requests Drawer -->
<div class="help-requests-drawer" id="helpRequestsDrawer">
    <div class="help-requests-panel">
        <div class="help-article-header">
            <h3 style="font-size:17px;font-weight:600;color:var(--text-heading);margin:0;">My Demo Support Requests</h3>
            <button type="button" class="modal-close-btn" onclick="document.getElementById('helpRequestsDrawer').classList.remove('show')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="help-article-body">
            <div class="settings-banner settings-banner-info">
                <div>Requests stored locally in this browser. Showing local demo copy.</div>
            </div>

            <div class="settings-table-wrapper">
                <table class="crm-table" id="helpRequestsTable">
                    <thead>
                        <tr>
                            <th data-protected="true" data-column-id="id">ID</th>
                            <th data-column-id="subject">Subject</th>
                            <th data-column-id="topic">Topic</th>
                            <th data-column-id="priority">Priority</th>
                            <th data-protected="true" data-column-id="actions">Action</th>
                        </tr>
                    </thead>
                    <tbody id="helpRequestsTbody">
                        <!-- Rendered dynamically via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Tutorial Preview Modal -->
<div class="help-modal-overlay" id="helpTutorialModal">
    <div class="help-modal-card">
        <div class="modal-header">
            <h3 class="modal-title" id="tutModalTitle">Tutorial Preview</h3>
            <button type="button" class="modal-close-btn" onclick="document.getElementById('helpTutorialModal').classList.remove('show')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body" style="text-align:center;padding:36px 24px;">
            <div style="width:56px;height:56px;border-radius:50%;background-color:var(--primary-light);color:var(--primary);display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;">
                <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            </div>
            <h4 style="font-size:16px;font-weight:700;color:var(--text-heading);margin-bottom:6px;">Tutorial Video Placeholder</h4>
            <p style="font-size:13.5px;color:var(--text-secondary);max-width:380px;margin:0 auto 16px;line-height:1.5;" id="tutModalDesc">Video tutorial content will be added in a future phase.</p>
            <div class="settings-banner settings-banner-info" style="text-align:left;margin-bottom:0;">
                <div>Video assets are not included in this frontend demonstration phase.</div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary btn-md" onclick="document.getElementById('helpTutorialModal').classList.remove('show')">Got It</button>
        </div>
    </div>
</div>

<!-- Embed Mock Data for Client-Side JS -->
<script>
window.HELP_MOCK_DATA = {
    categories: <?php echo json_encode($help_categories ?? []); ?>,
    articles: <?php echo json_encode($help_articles ?? []); ?>,
    tutorials: <?php echo json_encode($help_tutorials ?? []); ?>,
    faqs: <?php echo json_encode($help_faqs ?? []); ?>,
    troubleshooting: <?php echo json_encode($help_troubleshooting ?? []); ?>
};
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

