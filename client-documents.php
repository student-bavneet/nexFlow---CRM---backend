<?php
// client-documents.php
require_once __DIR__ . '/includes/client-auth.php';
require_once __DIR__ . '/includes/client-helpers.php';
require_once __DIR__ . '/includes/client-documents-data.php';

$client = require_client_auth();
$pdo = nexflow_db();

$page_title = "Shared Documents — NexFlow Client Portal";
$active_nav = "documents";
$page_heading = "Shared Documents & Files";

$docData = client_get_documents_data($pdo, (int)$client['organization_id'], (int)$client['company_id']);
$initialDocs = $docData['documents'] ?? [];
$categoryCounts = $docData['category_counts'] ?? [];

include __DIR__ . '/includes/client-header.php';
include __DIR__ . '/includes/client-sidebar.php';
?>

<div class="client-portal-main-wrapper">
    <?php include __DIR__ . '/includes/client-topbar.php'; ?>

    <main class="client-portal-content">
        
        <div style="margin-bottom:20px;">
            <p style="font-size:13.5px;color:#64748B;margin:4px 0 0 0;">Access Master Service Agreements, Statement of Work files, project deliverables, and upload compliance documents.</p>
        </div>

        <!-- Toolbar & View Switcher -->
        <div class="client-portal-toolbar">
            <div class="client-portal-toolbar-left" style="gap:10px;flex-wrap:wrap;">
                <div class="client-portal-search-wrapper" style="width:260px;">
                    <svg class="client-portal-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" class="client-portal-search-input" id="docsSearchInput" placeholder="Search documents..." oninput="window.clientDocuments.render()">
                </div>

                <select class="input-control" id="docCategoryFilter" style="width:160px;height:36px;" onchange="window.clientDocuments.render()">
                    <option value="All">All Categories</option>
                    <option value="Contracts">Contracts</option>
                    <option value="Projects">Projects</option>
                    <option value="Proposals">Proposals</option>
                    <option value="Invoices">Invoices</option>
                    <option value="Reports">Reports</option>
                    <option value="General">General</option>
                </select>
            </div>

            <div class="client-portal-toolbar-right">
                <div style="display:flex;gap:4px;background:#E2E8F0;padding:3px;border-radius:6px;">
                    <button type="button" class="btn btn-ghost btn-xs active" id="btnDocsGrid" onclick="window.clientDocuments.setViewMode('grid', this)" style="background:#FFFFFF;font-weight:600;">Grid</button>
                    <button type="button" class="btn btn-ghost btn-xs" id="btnDocsList" onclick="window.clientDocuments.setViewMode('list', this)">List</button>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.clientDocuments.openUploadModal()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Upload Document
                </button>
            </div>
        </div>

        <!-- Documents Container -->
        <div id="documentsContainer">
            <!-- Populated securely by JavaScript -->
        </div>

        <div style="margin-top:20px;padding:12px 16px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;font-size:12.5px;color:#64748B;">
            <svg width="16" height="16" fill="none" stroke="#2563EB" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            All shared files and compliance records are securely encrypted and synchronized directly with your company's dedicated NexFlow workspace.
        </div>

    </main>
</div>

<!-- CLIENT PORTAL: PREVIEW DOCUMENT MODAL -->
<div class="modal-overlay" id="clientDocPreviewModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.6);z-index:1100;align-items:center;justify-content:center;" onclick="if(event.target===this) window.clientDocuments.closePreviewModal()">
    <div style="background:#FFF;border-radius:12px;width:100%;max-width:680px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);border:1px solid #E2E8F0;overflow:hidden;" onclick="event.stopPropagation()">
        <div class="client-portal-modal-header" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #E2E8F0;">
            <div>
                <h3 class="client-portal-modal-title" id="cdpModalTitle" style="font-size:15.5px;font-weight:700;color:#0F172A;margin:0;">Document Preview</h3>
                <span style="font-size:12px;color:#64748B;" id="cdpModalCode">DOC</span>
            </div>
            <button type="button" class="client-portal-drawer-close" onclick="window.clientDocuments.closePreviewModal()" aria-label="Close preview" style="background:none;border:none;cursor:pointer;color:#64748B;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="client-portal-modal-body" id="cdpModalBody" style="padding:20px;overflow-y:auto;flex:1;">
            <!-- Rendered dynamically -->
        </div>
        <div class="client-portal-modal-footer" style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-top:1px solid #E2E8F0;background:#F8FAFC;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientDocuments.closePreviewModal()">Close</button>
            <div style="display:flex;gap:8px;">
                <button type="button" class="btn btn-secondary btn-sm" id="btnPreviewInTab" style="display:none;" onclick="window.clientDocuments.openPreviewTab()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    Open in Tab
                </button>
                <a class="btn btn-primary btn-sm" id="btnDownloadFromPreview" href="#" download style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download File
                </a>
            </div>
        </div>
    </div>
</div>

<!-- CLIENT PORTAL: UPLOAD DOCUMENT MODAL -->
<div class="modal-overlay" id="clientDocUploadModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.6);z-index:1100;align-items:center;justify-content:center;" onclick="if(event.target===this) window.clientDocuments.closeUploadModal()">
    <div style="background:#FFF;border-radius:12px;width:100%;max-width:500px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);border:1px solid #E2E8F0;overflow:hidden;" onclick="event.stopPropagation()">
        <div class="client-portal-modal-header" style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #E2E8F0;">
            <h3 class="client-portal-modal-title" style="font-size:16px;font-weight:700;color:#0F172A;margin:0;">Share Document with NexFlow Team</h3>
            <button type="button" class="client-portal-drawer-close" onclick="window.clientDocuments.closeUploadModal()" style="background:none;border:none;cursor:pointer;color:#64748B;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="clientDocUploadForm" onsubmit="window.clientDocuments.submitUpload(event)">
            <div class="client-portal-modal-body" style="padding:20px;">
                <div style="margin-bottom:16px;">
                    <label class="client-login-label" style="display:block;font-size:12.5px;font-weight:600;color:#334155;margin-bottom:6px;">Select File *</label>
                    <input type="file" class="input-control" id="clientFileInput" required style="width:100%;padding:6px 10px;font-size:13px;" accept=".pdf,.doc,.docx,.txt,.rtf,.png,.jpg,.jpeg,.zip,.xlsx,.xls,.pptx,.ppt,.csv">
                    <span style="font-size:11.5px;color:#94A3B8;margin-top:4px;display:block;">Supported: PDF, DOCX, XLSX, Images, ZIP, CSV (Max 25 MB)</span>
                </div>

                <div style="margin-bottom:16px;">
                    <label class="client-login-label" style="display:block;font-size:12.5px;font-weight:600;color:#334155;margin-bottom:6px;">Document Title</label>
                    <input type="text" class="input-control" id="clientDocTitleInput" placeholder="Leave empty to use original filename" style="width:100%;height:36px;font-size:13px;">
                </div>

                <div style="margin-bottom:10px;">
                    <label class="client-login-label" style="display:block;font-size:12.5px;font-weight:600;color:#334155;margin-bottom:6px;">Document Category *</label>
                    <select class="input-control" id="clientDocCategorySelect" style="width:100%;height:36px;font-size:13px;">
                        <option value="Contracts">Contracts</option>
                        <option value="Projects" selected>Projects</option>
                        <option value="Proposals">Proposals</option>
                        <option value="Invoices">Invoices</option>
                        <option value="Reports">Reports</option>
                        <option value="General">General</option>
                    </select>
                </div>
            </div>
            <div class="client-portal-modal-footer" style="display:flex;justify-content:flex-end;gap:10px;padding:14px 20px;border-top:1px solid #E2E8F0;background:#F8FAFC;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.clientDocuments.closeUploadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitClientUpload" style="display:inline-flex;align-items:center;gap:6px;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Upload Document</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
window.clientPortalCsrf = <?php echo json_encode(client_csrf_token()); ?>;
window.INITIAL_DOCUMENTS = <?php echo json_encode($initialDocs); ?>;

(function () {
    "use strict";

    let documentsList = Array.isArray(window.INITIAL_DOCUMENTS) ? [...window.INITIAL_DOCUMENTS] : [];
    let currentDocView = "grid";
    let activePreviewDoc = null;

    function escapeHtml(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function showToast(message, type = "success") {
        const toast = document.createElement("div");
        toast.className = "client-portal-toast toast-" + type;
        toast.style.cssText = "position:fixed;bottom:24px;right:24px;background:#0F172A;color:#FFFFFF;padding:12px 20px;border-radius:8px;font-size:13.5px;font-weight:500;box-shadow:0 10px 15px -3px rgba(0,0,0,0.2);z-index:9999;transition:opacity 0.3s ease;display:flex;align-items:center;gap:8px;";
        if (type === "error") {
            toast.style.background = "#DC2626";
        }
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = "0";
            setTimeout(() => toast.remove(), 350);
        }, 3000);
    }

    const clientDocsModule = {
        data: documentsList,

        setViewMode: function (mode, btn) {
            currentDocView = mode;
            document.querySelectorAll(".client-portal-toolbar-right button.btn-ghost").forEach(b => {
                b.style.background = "none";
                b.style.fontWeight = "500";
            });
            if (btn) {
                btn.style.background = "#FFFFFF";
                btn.style.fontWeight = "600";
            }
            this.render();
        },

        getFilteredDocuments: function () {
            const query = (document.getElementById("docsSearchInput")?.value || "").toLowerCase().trim();
            const category = document.getElementById("docCategoryFilter")?.value || "All";

            return documentsList.filter(doc => {
                const name = (doc.name || doc.title || "").toLowerCase();
                const deal = (doc.deal || doc.related_record || "").toLowerCase();
                const code = (doc.document_code || "").toLowerCase();
                const cat = (doc.category || "").toLowerCase();

                const matchesQuery = !query || name.includes(query) || deal.includes(query) || code.includes(query) || cat.includes(query);

                let matchesCategory = (category === "All");
                if (!matchesCategory) {
                    const sel = category.toLowerCase();
                    matchesCategory = cat.includes(sel) || sel.includes(cat);
                }

                return matchesQuery && matchesCategory;
            });
        },

        render: function () {
            const container = document.getElementById("documentsContainer");
            if (!container) return;

            const filtered = this.getFilteredDocuments();

            if (filtered.length === 0) {
                container.innerHTML = `
                    <div class="client-portal-card" style="padding:48px 24px;text-align:center;color:#64748B;">
                        <svg width="40" height="40" fill="none" stroke="#94A3B8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px auto;display:block;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <div style="font-weight:600;font-size:15px;color:#0F172A;margin-bottom:4px;">No documents match your query</div>
                        <p style="font-size:13px;color:#64748B;margin:0;">Try adjusting your search term or select "All Categories".</p>
                    </div>
                `;
                return;
            }

            if (currentDocView === "grid") {
                let html = '<div class="client-portal-docs-grid">';
                filtered.forEach(doc => {
                    const badgeClass = (doc.category === 'Contracts' || doc.type === 'Contract') ? 'blue'
                        : (doc.category === 'Projects' ? 'green'
                        : (doc.category === 'Proposals' ? 'purple' : 'gray'));

                    const downloadUrl = `api/client-documents.php?action=download&id=${encodeURIComponent(doc.id)}`;

                    html += `
                        <div class="client-portal-doc-card">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                                <div class="client-portal-doc-icon">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </div>
                                <span class="client-portal-badge ${badgeClass}">${escapeHtml(doc.category || doc.type || 'Document')}</span>
                            </div>
                            <div>
                                <div style="font-size:13.5px;font-weight:700;color:#0F172A;margin-bottom:4px;word-break:break-word;">${escapeHtml(doc.name || doc.title)}</div>
                                <div style="font-size:11.5px;color:#64748B;">Linked: ${escapeHtml(doc.deal || doc.related_record || 'Workspace')}</div>
                                <div style="font-size:11px;color:#94A3B8;margin-top:2px;">Size: ${escapeHtml(doc.size || doc.file_size)} • ${escapeHtml(doc.formatted_date || doc.date)}</div>
                            </div>
                            <div style="display:flex;gap:8px;margin-top:4px;">
                                <button type="button" class="btn btn-secondary btn-xs" style="flex:1;" onclick="window.clientDocuments.previewDoc(${doc.id})">Preview</button>
                                <a class="btn btn-ghost btn-xs" title="Download" href="${downloadUrl}" download style="display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                </a>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                // List View Table
                let html = `
                    <div class="client-portal-card">
                        <div class="client-portal-table-wrapper">
                            <table class="client-portal-table">
                                <thead>
                                    <tr>
                                        <th>Document Name</th>
                                        <th>Category</th>
                                        <th>Linked Record</th>
                                        <th>File Size</th>
                                        <th>Date Shared</th>
                                        <th>Uploaded By</th>
                                        <th style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                filtered.forEach(doc => {
                    const badgeClass = (doc.category === 'Contracts' || doc.type === 'Contract') ? 'blue'
                        : (doc.category === 'Projects' ? 'green'
                        : (doc.category === 'Proposals' ? 'purple' : 'gray'));

                    const downloadUrl = `api/client-documents.php?action=download&id=${encodeURIComponent(doc.id)}`;

                    html += `
                        <tr>
                            <td style="font-weight:600;color:#0F172A;">${escapeHtml(doc.name || doc.title)}</td>
                            <td><span class="client-portal-badge ${badgeClass}">${escapeHtml(doc.category || doc.type || 'Document')}</span></td>
                            <td>${escapeHtml(doc.deal || doc.related_record || '—')}</td>
                            <td>${escapeHtml(doc.size || doc.file_size)}</td>
                            <td>${escapeHtml(doc.formatted_date || doc.date)}</td>
                            <td>${escapeHtml(doc.uploadedBy || doc.uploaded_by || 'Account Team')}</td>
                            <td style="text-align:right;">
                                <button type="button" class="btn btn-secondary btn-xs" onclick="window.clientDocuments.previewDoc(${doc.id})">Preview</button>
                                <a class="btn btn-ghost btn-xs" href="${downloadUrl}" download style="display:inline-flex;align-items:center;text-decoration:none;">Download</a>
                            </td>
                        </tr>
                    `;
                });
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                container.innerHTML = html;
            }
        },

        previewDoc: async function (docId) {
            const doc = documentsList.find(d => Number(d.id) === Number(docId));
            if (!doc) return;
            activePreviewDoc = doc;

            const modalTitle = document.getElementById("cdpModalTitle");
            const modalCode = document.getElementById("cdpModalCode");
            const modalBody = document.getElementById("cdpModalBody");
            const btnDownload = document.getElementById("btnDownloadFromPreview");
            const btnTab = document.getElementById("btnPreviewInTab");

            if (modalTitle) modalTitle.textContent = doc.name || doc.title;
            if (modalCode) modalCode.textContent = `${doc.document_code} • ${doc.category || doc.type}`;
            if (btnDownload) {
                btnDownload.href = `api/client-documents.php?action=download&id=${encodeURIComponent(doc.id)}`;
            }

            const isPdf = (doc.file_extension === 'pdf') || (doc.mime_type === 'application/pdf');
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(doc.file_extension) || doc.mime_type.startsWith('image/');
            const previewUrl = `api/client-documents.php?action=preview&id=${encodeURIComponent(doc.id)}`;

            if (btnTab) {
                btnTab.style.display = (isPdf || isImage) ? "inline-flex" : "none";
            }

            let previewContent = '';
            if (isPdf) {
                previewContent = `
                    <div style="margin-bottom:14px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            <div style="font-size:13.5px;font-weight:600;color:#0F172A;">${escapeHtml(doc.name || doc.title)}</div>
                            <div style="font-size:12px;color:#64748B;">Category: ${escapeHtml(doc.category)} • Size: ${escapeHtml(doc.size || doc.file_size)} • Uploaded by: ${escapeHtml(doc.uploadedBy || 'Account Team')}</div>
                        </div>
                        <span class="client-portal-badge green">Verified PDF</span>
                    </div>
                    <div style="width:100%;height:380px;background:#475569;border-radius:8px;overflow:hidden;border:1px solid #CBD5E1;">
                        <iframe src="${previewUrl}#toolbar=0" style="width:100%;height:100%;border:none;" title="PDF Preview"></iframe>
                    </div>
                `;
            } else if (isImage) {
                previewContent = `
                    <div style="margin-bottom:14px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            <div style="font-size:13.5px;font-weight:600;color:#0F172A;">${escapeHtml(doc.name || doc.title)}</div>
                            <div style="font-size:12px;color:#64748B;">Category: ${escapeHtml(doc.category)} • Size: ${escapeHtml(doc.size || doc.file_size)}</div>
                        </div>
                        <span class="client-portal-badge blue">Verified Image</span>
                    </div>
                    <div style="text-align:center;padding:16px;background:#0F172A;border-radius:8px;max-height:380px;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                        <img src="${previewUrl}" alt="${escapeHtml(doc.name)}" style="max-width:100%;max-height:340px;object-fit:contain;border-radius:4px;">
                    </div>
                `;
            } else {
                // Formats requiring download (DOCX, XLSX, etc.)
                previewContent = `
                    <div style="background-color:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:36px 20px;text-align:center;margin-bottom:16px;">
                        <div style="width:52px;height:52px;border-radius:10px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;margin:0 auto 14px auto;">
                            <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <h4 style="font-size:15px;font-weight:700;color:#0F172A;margin:0 0 6px 0;">${escapeHtml(doc.name || doc.title)}</h4>
                        <p style="font-size:12.5px;color:#64748B;margin:0 0 14px 0;">Category: ${escapeHtml(doc.category)} • File Size: ${escapeHtml(doc.size || doc.file_size)} • Uploaded by: ${escapeHtml(doc.uploadedBy || 'Account Team')}</p>
                        <div style="display:inline-block;padding:8px 14px;background:#FFFBEB;border:1px solid #FDE68A;border-radius:6px;font-size:12px;color:#B45309;font-weight:500;">
                            ⚠️ Browser preview is unavailable for .${escapeHtml(doc.file_extension || 'file')} format. Please download the document to view full formatting.
                        </div>
                    </div>
                `;
            }

            if (modalBody) modalBody.innerHTML = previewContent;

            const modal = document.getElementById("clientDocPreviewModal");
            if (modal) {
                modal.style.display = "flex";
                document.body.style.overflow = "hidden";
            }
        },

        openPreviewTab: function () {
            if (activePreviewDoc) {
                window.open(`api/client-documents.php?action=preview&id=${encodeURIComponent(activePreviewDoc.id)}`, '_blank');
            }
        },

        closePreviewModal: function () {
            const modal = document.getElementById("clientDocPreviewModal");
            if (modal) modal.style.display = "none";
            document.body.style.overflow = "";
            activePreviewDoc = null;
        },

        openUploadModal: function () {
            const form = document.getElementById("clientDocUploadForm");
            if (form) form.reset();
            const modal = document.getElementById("clientDocUploadModal");
            if (modal) {
                modal.style.display = "flex";
                document.body.style.overflow = "hidden";
            }
        },

        closeUploadModal: function () {
            const modal = document.getElementById("clientDocUploadModal");
            if (modal) modal.style.display = "none";
            document.body.style.overflow = "";
        },

        submitUpload: async function (e) {
            e.preventDefault();
            const fileInput = document.getElementById("clientFileInput");
            if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                showToast("Please select a file to upload.", "error");
                return;
            }

            const title = (document.getElementById("clientDocTitleInput")?.value || "").trim();
            const category = document.getElementById("clientDocCategorySelect")?.value || "Projects";

            const formData = new FormData();
            formData.append("action", "upload");
            formData.append("csrf_token", window.clientPortalCsrf || "");
            formData.append("title", title);
            formData.append("category", category);
            formData.append("file", fileInput.files[0]);

            const submitBtn = document.getElementById("btnSubmitClientUpload");
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.querySelector("span").textContent = "Uploading...";
            }

            try {
                const resp = await fetch("api/client-documents.php", {
                    method: "POST",
                    headers: {
                        "X-CSRF-Token": window.clientPortalCsrf || "",
                        "Accept": "application/json"
                    },
                    body: formData
                });

                const res = await resp.json();

                if (res && res.success && res.data && res.data.document) {
                    this.closeUploadModal();
                    showToast(`Document "${res.data.document.title}" uploaded successfully!`, "success");

                    // Refresh data list from API
                    await this.reloadDocuments();
                } else {
                    showToast(res?.message || "Failed to upload document.", "error");
                }
            } catch (err) {
                console.error("Client document upload error:", err);
                showToast("Server communication error. Please try again.", "error");
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.querySelector("span").textContent = "Upload Document";
                }
            }
        },

        reloadDocuments: async function () {
            try {
                const resp = await fetch("api/client-documents.php?action=list", {
                    headers: { "Accept": "application/json" }
                });
                const res = await resp.json();
                if (res && res.success && res.data && Array.isArray(res.data.documents)) {
                    documentsList = res.data.documents;
                    this.data = documentsList;
                    this.render();
                }
            } catch (err) {
                console.warn("Failed to reload documents:", err);
            }
        }
    };

    window.clientDocuments = clientDocsModule;

    // Harmonize window.clientPortal handlers so any other calls route through live module
    if (window.clientPortal) {
        window.clientPortal.openUploadDocModal = function () {
            window.clientDocuments.openUploadModal();
        };
        window.clientPortal.previewDocument = function (id) {
            window.clientDocuments.previewDoc(id);
        };
        window.clientPortal.downloadDemoDoc = function (name) {
            // Find document by name or id and download
            const doc = documentsList.find(d => d.name === name || d.title === name);
            if (doc) {
                window.location.href = `api/client-documents.php?action=download&id=${encodeURIComponent(doc.id)}`;
            } else {
                showToast("Downloading file: " + name, "info");
            }
        };
    }

    document.addEventListener("DOMContentLoaded", () => {
        window.clientDocuments.render();
    });
})();
</script>

<?php include __DIR__ . '/includes/client-footer.php'; ?>
