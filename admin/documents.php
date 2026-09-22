<?php
// Documents Management Page
$page_title = "Documents";
$current_page = "documents";

include __DIR__ . '/includes/header.php';
requirePermission('documents');
include __DIR__ . '/includes/sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<main class="main-content documents-page">
    <div class="page-container">
        
        <!-- Page Header -->
        <div class="documents-header">
            <div class="documents-header-title-area">
                <div class="documents-title-row">
                    <h1 class="documents-header-title">Documents</h1>
                    <span class="documents-total-badge" id="docTotalCountBadge">0 documents</span>
                </div>
                <p class="documents-header-subtitle">Manage files, documents, and client-related records across your CRM.</p>
            </div>
            <div class="documents-header-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.documentsApp.exportCSV()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export CSV
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.documentsApp.openUploadDrawer()">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                     Upload Document
                </button>
            </div>
        </div>

        <!-- 5-Card Summary Bar -->
        <div class="documents-summary-bar">
            <div class="documents-summary-card" data-kpi="All" onclick="window.documentsApp.filterByKPI('All')">
                <div class="documents-summary-icon" style="background-color: #EFF6FF; color: #2563EB;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <div class="documents-summary-info">
                    <span class="documents-summary-val" id="kpiTotalDocs">0</span>
                    <span class="documents-summary-lbl">Total Documents</span>
                </div>
            </div>

            <div class="documents-summary-card" data-kpi="Shared" onclick="window.documentsApp.filterByKPI('Shared')">
                <div class="documents-summary-icon" style="background-color: #ECFDF5; color: #047857;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </div>
                <div class="documents-summary-info">
                    <span class="documents-summary-val" id="kpiSharedDocs">0</span>
                    <span class="documents-summary-lbl">Shared</span>
                </div>
            </div>

            <div class="documents-summary-card" data-kpi="Pending" onclick="window.documentsApp.filterByKPI('Pending')">
                <div class="documents-summary-icon" style="background-color: #FEF3C7; color: #D97706;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="documents-summary-info">
                    <span class="documents-summary-val" id="kpiPendingDocs">0</span>
                    <span class="documents-summary-lbl">Pending Review</span>
                </div>
            </div>

            <div class="documents-summary-card" data-kpi="Recent" onclick="window.documentsApp.filterByKPI('Recent')">
                <div class="documents-summary-icon" style="background-color: #F3E8FF; color: #7C3AED;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <div class="documents-summary-info">
                    <span class="documents-summary-val" id="kpiRecentDocs">0</span>
                    <span class="documents-summary-lbl">Recently Added</span>
                </div>
            </div>

            <div class="documents-summary-card" data-kpi="Archived" onclick="window.documentsApp.filterByKPI('Archived')">
                <div class="documents-summary-icon" style="background-color: #FEF2F2; color: #DC2626;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                </div>
                <div class="documents-summary-info">
                    <span class="documents-summary-val" id="kpiArchivedDocs">0</span>
                    <span class="documents-summary-lbl">Archived</span>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="documents-toolbar">
            <div class="documents-search-wrapper" style="position:relative; flex:1; min-width:220px;">
                <svg class="documents-search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text-muted); pointer-events:none;">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" class="input-control input-sm" id="docSearchInput" placeholder="Search documents, clients, projects..." style="padding-left:32px; width:100%;" oninput="window.documentsApp.onSearchInput(this.value)">
                <button type="button" class="documents-search-clear" id="docClearSearchBtn" onclick="window.documentsApp.clearSearch()">&times;</button>
            </div>

            <select class="input-control input-sm" id="docTypeFilter" style="width:125px; flex-shrink:0;" onchange="window.documentsApp.onFilterChange()">
                <option value="All">All Types</option>
                <option value="PDF">PDF</option>
                <option value="DOCX">DOCX</option>
                <option value="XLSX">XLSX</option>
                <option value="PPTX">PPTX</option>
                <option value="Image">Image</option>
                <option value="Other">Other</option>
            </select>

            <select class="input-control input-sm" id="docClientFilter" style="width:135px; flex-shrink:0;" onchange="window.documentsApp.onFilterChange()">
                <option value="All">All Clients</option>
            </select>

            <select class="input-control input-sm" id="docCategoryFilter" style="width:135px; flex-shrink:0;" onchange="window.documentsApp.onFilterChange()">
                <option value="All">All Categories</option>
                <option value="Contracts">Contracts</option>
                <option value="Proposals">Proposals</option>
                <option value="Invoices">Invoices</option>
                <option value="Projects">Projects</option>
                <option value="Reports">Reports</option>
                <option value="General">General</option>
            </select>

            <select class="input-control input-sm" id="docOwnerFilter" style="width:130px; flex-shrink:0;" onchange="window.documentsApp.onFilterChange()">
                <option value="All">All Owners</option>
            </select>

            <!-- Customize Columns Dropdown -->
            <div class="table-columns-dropdown-wrapper" style="flex-shrink:0;">
                <button type="button" class="btn btn-secondary btn-sm table-columns-btn" id="btnCustomizeDocColumns" onclick="window.documentsApp.toggleCustomizeColumnsMenu(event)">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/><path d="M3 3h7v18H3z"/></svg>
                    Customize Columns
                </button>
                
                <div class="table-columns-dropdown-menu" id="docColumnsDropdownMenu" onclick="event.stopPropagation()">
                    <div class="table-columns-dropdown-header">REORDER &amp; VISIBILITY</div>
                    <div class="table-columns-list" id="docColumnsList">
                        <!-- Populated dynamically by assets/js/documents.js -->
                    </div>
                    <div class="table-columns-dropdown-footer">
                        <button type="button" class="table-columns-footer-btn" onclick="window.documentsApp.selectAllColumns()">Show All</button>
                        <button type="button" class="table-columns-footer-btn" onclick="window.documentsApp.hideOptionalColumns()">Hide Optional</button>
                        <button type="button" class="table-columns-footer-btn" onclick="window.documentsApp.resetColumnsToDefault()">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Documents Table Container -->
        <div class="documents-table-container">
            <table class="documents-table">
                <thead id="documentsThead">
                    <!-- Populated dynamically by assets/js/documents.js -->
                </thead>
                <tbody id="documentsTbody">
                    <!-- Populated dynamically by assets/js/documents.js -->
                </tbody>
            </table>

            <!-- Pagination Bar -->
            <div class="pagination-container">
                <div>Showing <span style="font-weight: 600; color: var(--text-body);" id="pagingRange">1–8</span> of <span style="font-weight: 600; color: var(--text-body);" id="pagingTotal">0</span></div>
                <div class="pagination-controls" id="paginationControls">
                    <!-- Populated dynamically by JS -->
                </div>
            </div>
        </div>

    </div>
</main>

<!-- Right-Side Document Details Drawer -->
<div class="documents-drawer-overlay" id="docDetailsDrawer" role="dialog" aria-modal="true" onclick="if(event.target===this) window.documentsApp.closeViewDrawer()">
    <div class="documents-drawer-panel" onclick="event.stopPropagation()">
        <div class="documents-drawer-header">
            <div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <div id="dvdStatusBadge"></div>
                </div>
                <h2 class="documents-drawer-title" id="dvdTitle">Document Details</h2>
            </div>
            <button type="button" class="documents-drawer-close" onclick="window.documentsApp.closeViewDrawer()" aria-label="Close drawer">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="documents-drawer-body" id="docDrawerBody">
            <!-- Dynamically injected document details -->
        </div>

        <div class="documents-drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.documentsApp.closeViewDrawer()">Close</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.documentsApp.openEditDrawer()">Edit</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.documentsApp.triggerPreview()">Preview</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.documentsApp.triggerShare()">Share</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.documentsApp.triggerDownload()">Download</button>
        </div>
    </div>
</div>

<!-- Upload / Edit Document Drawer -->
<div class="documents-drawer-overlay" id="docUploadDrawer" role="dialog" aria-modal="true" onclick="if(event.target===this) window.documentsApp.closeUploadDrawer()">
    <div class="documents-drawer-panel" onclick="event.stopPropagation()">
        <div class="documents-drawer-header">
            <div>
                <h2 class="documents-drawer-title" id="docModalTitle">Upload Document</h2>
                <div class="documents-drawer-subtitle" id="docModalSubtitle">Attach files and assign client, category, and CRM context.</div>
            </div>
            <button type="button" class="documents-drawer-close" onclick="window.documentsApp.closeUploadDrawer()">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="documents-drawer-body">
            <form id="docUploadForm" onsubmit="event.preventDefault(); window.documentsApp.saveDocumentSubmit();">
                <input type="hidden" id="editDocId" value="">

                <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px;">File Information</div>
                
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Document Name *</label>
                    <input type="text" class="input-control input-sm" id="fieldDocTitle" required placeholder="e.g. Project Proposal - E-Commerce Platform">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Upload File</label>
                    <input type="file" class="input-control input-sm" id="fieldDocFile" name="file" style="padding:4px 8px;">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label class="form-label">Document Type</label>
                        <select class="input-control input-sm" id="fieldDocType">
                            <option value="PDF" selected>PDF</option>
                            <option value="DOCX">DOCX</option>
                            <option value="XLSX">XLSX</option>
                            <option value="PPTX">PPTX</option>
                            <option value="Image">Image</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Client / Company *</label>
                        <select class="input-control input-sm" id="fieldDocClient">
                            <option value="">Select Client / Company</option>
                        </select>
                    </div>
                </div>

                <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;margin-top:16px;margin-bottom:8px;">Categorization & Context</div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label class="form-label">Category</label>
                        <select class="input-control input-sm" id="fieldDocCategory">
                            <option value="Contracts">Contracts</option>
                            <option value="Proposals" selected>Proposals</option>
                            <option value="Invoices">Invoices</option>
                            <option value="Projects">Projects</option>
                            <option value="Reports">Reports</option>
                            <option value="General">General</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Related Record</label>
                        <select class="input-control input-sm" id="fieldDocRelated">
                            <option value="">None Linked</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label class="form-label">Internal Owner</label>
                        <select class="input-control input-sm" id="fieldDocOwner">
                            <option value="">Select Internal Owner</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select class="input-control input-sm" id="fieldDocStatus">
                            <option value="Available" selected>Available</option>
                            <option value="Shared">Shared</option>
                            <option value="Signed">Signed</option>
                            <option value="Pending Review">Pending Review</option>
                            <option value="Internal">Internal</option>
                            <option value="Archived">Archived</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">Description & Notes</label>
                    <textarea class="input-control" id="fieldDocDesc" style="height:70px;font-size:12.5px;resize:none;" placeholder="Enter optional file description..."></textarea>
                </div>
            </form>
        </div>

        <div class="documents-drawer-footer">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.documentsApp.closeUploadDrawer()">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnSaveDocSubmit" onclick="window.documentsApp.saveDocumentSubmit()">Upload Document</button>
        </div>
    </div>
</div>

<script src="assets/js/documents.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
