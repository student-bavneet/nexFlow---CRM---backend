/**
 * NexFlow CRM — Admin Documents Management Controller
 * 
 * Fully database-backed controller for admin/documents.php.
 * Connects directly to admin/api/documents.php for multi-tenant MySQL storage,
 * live SQL KPI aggregation, authenticated file preview and downloads,
 * multipart physical file uploads, server-side filtering, CSV export,
 * and column customization.
 */

(function () {
    "use strict";

    const API_URL = "api/documents.php";
    const COLUMNS_KEY = "NexFlow_admin_documents_columns_v1";

    const defaultColumnOrder = ["document", "client", "category", "relatedRecord", "owner", "lastModified", "fileSize", "status", "actions"];
    const columnLabels = {
        document: "Document",
        client: "Client / Company",
        category: "Category",
        relatedRecord: "Related Record",
        owner: "Owner",
        lastModified: "Last Modified",
        fileSize: "Size",
        status: "Status",
        actions: "Actions"
    };

    let columnOrder = [...defaultColumnOrder];
    let columnVisibility = {
        document: true,
        client: true,
        category: true,
        relatedRecord: true,
        owner: true,
        lastModified: true,
        fileSize: true,
        status: true,
        actions: true
    };

    let documents = [];
    let referenceOptions = {
        companies: [],
        users: [],
        document_types: ['PDF', 'DOCX', 'XLSX', 'PPTX', 'Image', 'Other'],
        categories: ['Contracts', 'Proposals', 'Invoices', 'Projects', 'Reports', 'General'],
        statuses: ['Shared', 'Signed', 'Available', 'Pending Review', 'Internal', 'Archived']
    };

    let activeDocForView = null;
    let currentDocPage = 1;
    let currentKpiFilter = "All";
    const DOCS_PER_PAGE = 8;
    let totalItems = 0;
    let totalPages = 1;
    let searchDebounceTimer = null;

    const AVATAR_COLORS = ['#2563EB', '#059669', '#7C3AED', '#D97706', '#DC2626', '#0284C7', '#4F46E5', '#0D9488'];

    function getAvatarColor(str) {
        if (!str) return '#2563EB';
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
    }

    function getInitials(name) {
        if (!name) return '—';
        const parts = name.trim().split(/\s+/);
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    function showAlert(title, msg, type = 'warning') {
        if (typeof showAlertModal === 'function') {
            showAlertModal({ title: title, message: msg, type: type });
        } else {
            alert(msg);
        }
    }

    async function apiRequest(endpoint, options = {}) {
        try {
            const resp = await fetch(endpoint, {
                headers: {
                    'Accept': 'application/json',
                    ...(options.headers || {})
                },
                ...options
            });
            const data = await resp.json();
            return data;
        } catch (err) {
            console.error('Documents API Error:', err);
            return { success: false, message: 'Network or server communication error.' };
        }
    }

    function loadColumnsConfig() {
        const stored = localStorage.getItem(COLUMNS_KEY);
        if (stored) {
            try {
                const parsed = JSON.parse(stored);
                if (parsed.order && Array.isArray(parsed.order)) columnOrder = parsed.order;
                if (parsed.visibility) columnVisibility = parsed.visibility;
            } catch (e) {
                console.error("Failed to parse column config:", e);
            }
        }
    }

    function saveColumnsConfig() {
        localStorage.setItem(COLUMNS_KEY, JSON.stringify({
            order: columnOrder,
            visibility: columnVisibility
        }));
    }

    function getFileTypeBadge(fileType) {
        let cssClass = "file-type-other";
        let label = fileType || "FILE";

        if (fileType === "PDF") cssClass = "file-type-pdf";
        else if (fileType === "DOCX" || fileType === "DOC") cssClass = "file-type-docx";
        else if (fileType === "XLSX" || fileType === "XLS" || fileType === "CSV") cssClass = "file-type-xlsx";
        else if (fileType === "PPTX" || fileType === "PPT") cssClass = "file-type-pptx";
        else if (fileType === "Image" || fileType === "PNG" || fileType === "JPG") cssClass = "file-type-img";

        return `<div class="document-file-icon ${cssClass}">${escapeHtml(label)}</div>`;
    }

    function getStatusBadgeHtml(status) {
        let cssClass = "badge-available";
        if (status === "Shared") cssClass = "badge-shared";
        else if (status === "Signed") cssClass = "badge-signed";
        else if (status === "Available") cssClass = "badge-available";
        else if (status === "Pending Review") cssClass = "badge-pending";
        else if (status === "Internal") cssClass = "badge-internal";
        else if (status === "Archived") cssClass = "badge-archived";

        return `<span class="doc-badge ${cssClass}">${escapeHtml(status)}</span>`;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        try {
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        } catch (e) {
            return dateStr;
        }
    }

    function renderTableHeader() {
        const thead = document.getElementById("documentsThead");
        if (!thead) return;

        let html = "<tr>";
        columnOrder.forEach(colKey => {
            if (!columnVisibility[colKey]) return;
            const label = columnLabels[colKey] || colKey;

            if (colKey === "document") {
                html += `<th style="min-width:240px;">${escapeHtml(label)}</th>`;
            } else if (colKey === "client") {
                html += `<th style="min-width:140px;">${escapeHtml(label)}</th>`;
            } else if (colKey === "category") {
                html += `<th style="min-width:120px;">${escapeHtml(label)}</th>`;
            } else if (colKey === "relatedRecord") {
                html += `<th style="min-width:160px;">${escapeHtml(label)}</th>`;
            } else if (colKey === "owner") {
                html += `<th style="min-width:140px;">${escapeHtml(label)}</th>`;
            } else if (colKey === "lastModified") {
                html += `<th style="min-width:110px;">${escapeHtml(label)}</th>`;
            } else if (colKey === "fileSize") {
                html += `<th style="min-width:90px;">${escapeHtml(label)}</th>`;
            } else if (colKey === "status") {
                html += `<th style="min-width:110px;">${escapeHtml(label)}</th>`;
            } else if (colKey === "actions") {
                html += `<th style="min-width:80px;text-align:right;">${escapeHtml(label)}</th>`;
            }
        });
        html += "</tr>";
        thead.innerHTML = html;
    }

    function renderTableBody() {
        const tbody = document.getElementById("documentsTbody");
        if (!tbody) return;

        const startIndex = totalItems === 0 ? 0 : (currentDocPage - 1) * DOCS_PER_PAGE;
        const endIndex = Math.min(currentDocPage * DOCS_PER_PAGE, totalItems);

        const pagingRange = document.getElementById("pagingRange");
        const pagingTotal = document.getElementById("pagingTotal");
        if (pagingRange) pagingRange.textContent = totalItems === 0 ? "0" : `${startIndex + 1}–${endIndex}`;
        if (pagingTotal) pagingTotal.textContent = totalItems;
        const container = document.querySelector('.pagination-container');
        if (container) {
            container.style.display = totalItems > DOCS_PER_PAGE ? 'flex' : 'none';
        }

        renderDocPaginationControls(totalItems, totalPages);

        const visibleCols = columnOrder.filter(k => columnVisibility[k]);

        if (!documents || documents.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${visibleCols.length || 1}" style="text-align:center;padding:40px 20px;color:var(--text-muted);">
                        <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom:8px;opacity:0.5;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <div style="font-weight:600;font-size:14px;color:var(--text-heading);">No documents found</div>
                        <div style="font-size:12px;margin-top:4px;">Upload a document or clear filters.</div>
                    </td>
                </tr>
            `;
            return;
        }

        let html = "";
        documents.forEach(doc => {
            const ownerName = doc.owner_name || "Unassigned";
            const ownerInitials = getInitials(ownerName);
            const ownerColor = getAvatarColor(ownerName);
            const clientName = doc.company_name || "—";
            const formattedDate = formatDate(doc.updated_at || doc.created_at);

            html += `<tr onclick="window.documentsApp.openViewDrawer(${doc.id})" style="cursor:pointer;">`;
            columnOrder.forEach(colKey => {
                if (!columnVisibility[colKey]) return;

                if (colKey === "document") {
                    html += `
                        <td>
                            <div class="document-file-cell">
                                ${getFileTypeBadge(doc.document_type)}
                                <div class="document-file-info">
                                    <span class="document-file-title">${escapeHtml(doc.title)}</span>
                                    <span class="document-file-meta">${escapeHtml(doc.document_type || 'File')} • ${escapeHtml(doc.file_size || '0 KB')}</span>
                                </div>
                            </div>
                        </td>
                    `;
                } else if (colKey === "client") {
                    html += `<td style="font-weight:600;color:var(--text-heading);">${escapeHtml(clientName)}</td>`;
                } else if (colKey === "category") {
                    html += `<td><span style="font-size:12px;font-weight:500;color:var(--text-secondary);background:#F8FAFC;border:1px solid #E2E8F0;padding:2px 6px;border-radius:4px;">${escapeHtml(doc.category || 'General')}</span></td>`;
                } else if (colKey === "relatedRecord") {
                    html += `<td style="color:var(--primary);font-size:12.5px;font-weight:500;">${escapeHtml(doc.display_related_record || doc.related_record || '—')}</td>`;
                } else if (colKey === "owner") {
                    html += `
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <span style="width:22px;height:22px;border-radius:50%;background:${ownerColor};color:#FFF;font-size:10px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;">${escapeHtml(ownerInitials)}</span>
                                <span style="font-size:12.5px;">${escapeHtml(ownerName)}</span>
                            </div>
                        </td>
                    `;
                } else if (colKey === "lastModified") {
                    html += `<td style="font-size:12.5px;color:var(--text-secondary);">${escapeHtml(formattedDate)}</td>`;
                } else if (colKey === "fileSize") {
                    html += `<td style="font-size:12.5px;color:var(--text-muted);">${escapeHtml(doc.file_size || '0 KB')}</td>`;
                } else if (colKey === "status") {
                    html += `<td>${getStatusBadgeHtml(doc.status)}</td>`;
                } else if (colKey === "actions") {
                    html += `
                        <td style="text-align:right;" onclick="event.stopPropagation()">
                            <button type="button" class="btn btn-secondary btn-xs" onclick="window.documentsApp.openViewDrawer(${doc.id})">View</button>
                        </td>
                    `;
                }
            });
            html += "</tr>";
        });

        tbody.innerHTML = html;
    }

    function renderDocPaginationControls(totalItems, totalPages) {
        const controls = document.getElementById("paginationControls");
        if (!controls) return;

        if (totalItems === 0) {
            controls.innerHTML = `
                <button class="pagination-btn" id="prevPageBtn" disabled>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button class="pagination-btn" id="nextPageBtn" disabled>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            `;
            return;
        }

        let html = `
            <button class="pagination-btn" id="prevPageBtn" ${currentDocPage === 1 ? 'disabled' : ''} onclick="window.documentsApp.goToPage(${currentDocPage - 1})">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
        `;

        const pageNumbers = getPaginationPages(currentDocPage, totalPages);
        pageNumbers.forEach(p => {
            if (p === '...') {
                html += `<span class="pagination-btn" style="border:none;background:none;cursor:default;">...</span>`;
            } else {
                const isActive = (p === currentDocPage);
                html += `<button type="button" class="pagination-btn ${isActive ? 'active' : ''}" onclick="window.documentsApp.goToPage(${p})">${p}</button>`;
            }
        });

        html += `
            <button class="pagination-btn" id="nextPageBtn" ${currentDocPage === totalPages ? 'disabled' : ''} onclick="window.documentsApp.goToPage(${currentDocPage + 1})">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        `;

        controls.innerHTML = html;
    }

    function getPaginationPages(current, total) {
        if (total <= 7) {
            const pages = [];
            for (let i = 1; i <= total; i++) pages.push(i);
            return pages;
        }
        if (current <= 4) {
            return [1, 2, 3, 4, 5, '...', total];
        }
        if (current >= total - 3) {
            return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
        }
        return [1, '...', current - 1, current, current + 1, '...', total];
    }

    function populateDropdowns() {
        // Toolbar Client Filter
        const docClientFilter = document.getElementById("docClientFilter");
        if (docClientFilter) {
            const currentVal = docClientFilter.value;
            docClientFilter.innerHTML = `<option value="All">All Clients</option>` +
                referenceOptions.companies.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join("");
            if (currentVal && docClientFilter.querySelector(`option[value="${currentVal}"]`)) {
                docClientFilter.value = currentVal;
            }
        }

        // Toolbar Owner Filter
        const docOwnerFilter = document.getElementById("docOwnerFilter");
        if (docOwnerFilter) {
            const currentVal = docOwnerFilter.value;
            docOwnerFilter.innerHTML = `<option value="All">All Owners</option>` +
                referenceOptions.users.map(u => `<option value="${u.id}">${escapeHtml(u.first_name + ' ' + (u.last_name || '')).trim()}</option>`).join("");
            if (currentVal && docOwnerFilter.querySelector(`option[value="${currentVal}"]`)) {
                docOwnerFilter.value = currentVal;
            }
        }

        // Upload Drawer Client Select
        const fieldDocClient = document.getElementById("fieldDocClient");
        if (fieldDocClient) {
            fieldDocClient.innerHTML = `<option value="">Select Client / Company</option>` +
                referenceOptions.companies.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join("");
        }

        // Upload Drawer Owner Select
        const fieldDocOwner = document.getElementById("fieldDocOwner");
        if (fieldDocOwner) {
            fieldDocOwner.innerHTML = `<option value="">Select Internal Owner</option>` +
                referenceOptions.users.map(u => `<option value="${u.id}">${escapeHtml(u.first_name + ' ' + (u.last_name || '')).trim()}</option>`).join("");
        }
    }

    window.documentsApp = {
        init: async function () {
            loadColumnsConfig();
            this.highlightActiveKpiCard();
            await this.loadReferenceOptions();
            await this.loadSummary();
            await this.loadDocuments();

            const catSelect = document.getElementById("fieldDocCategory");
            if (catSelect) {
                catSelect.addEventListener("change", () => {
                    this.loadRelatedOptions();
                });
            }

            const clientSelect = document.getElementById("fieldDocClient");
            if (clientSelect) {
                clientSelect.addEventListener("change", () => {
                    const cat = document.getElementById("fieldDocCategory")?.value;
                    if (cat === "Proposals" || cat === "Contracts" || cat === "Invoices") {
                        this.loadRelatedOptions();
                    }
                });
            }
        },

        loadRelatedOptions: async function (selectedId = null, selectedType = null) {
            const categorySelect = document.getElementById("fieldDocCategory");
            const clientSelect   = document.getElementById("fieldDocClient");
            const relatedSelect  = document.getElementById("fieldDocRelated");

            if (!relatedSelect) return;

            const category = categorySelect ? categorySelect.value : "";
            const companyId = clientSelect ? clientSelect.value : "";

            const supportedCategories = ['Projects', 'Deals', 'Proposals', 'Contracts', 'Invoices'];
            if (!supportedCategories.includes(category)) {
                relatedSelect.innerHTML = `<option value="">None Linked</option>`;
                return;
            }

            let url = `${API_URL}?action=related_options&category=${encodeURIComponent(category)}`;
            if (companyId) {
                url += `&company_id=${encodeURIComponent(companyId)}`;
            }

            const res = await apiRequest(url);
            let options = [];
            if (res && res.success && Array.isArray(res.data?.options)) {
                options = res.data.options;
            }

            let html = `<option value="">None Linked</option>`;
            let matched = false;

            options.forEach(opt => {
                const isSelected = selectedId && String(opt.id) === String(selectedId) && (!selectedType || opt.type === selectedType);
                if (isSelected) matched = true;
                html += `<option value="${opt.id}" data-type="${escapeHtml(opt.type)}" ${isSelected ? 'selected' : ''}>${escapeHtml(opt.label)}</option>`;
            });

            // If selectedId wasn't found in the current options list, but we had a valid existing label:
            if (selectedId && !matched && activeDocForView && activeDocForView.related_record && (!selectedType || activeDocForView.related_type === selectedType)) {
                html += `<option value="${selectedId}" data-type="${escapeHtml(selectedType || '')}" selected>${escapeHtml(activeDocForView.related_record)}</option>`;
            }

            relatedSelect.innerHTML = html;
        },

        loadReferenceOptions: async function () {
            const res = await apiRequest(`${API_URL}?action=reference_options`);
            if (res && res.success && res.data) {
                referenceOptions = res.data;
                populateDropdowns();
            }
        },

        loadSummary: async function () {
            const res = await apiRequest(`${API_URL}?action=summary`);
            if (res && res.success && res.data) {
                const kpi = res.data;
                if (document.getElementById("kpiTotalDocs")) document.getElementById("kpiTotalDocs").textContent = kpi.total || 0;
                if (document.getElementById("kpiSharedDocs")) document.getElementById("kpiSharedDocs").textContent = kpi.shared || 0;
                if (document.getElementById("kpiPendingDocs")) document.getElementById("kpiPendingDocs").textContent = kpi.pending || 0;
                if (document.getElementById("kpiRecentDocs")) document.getElementById("kpiRecentDocs").textContent = kpi.recent || 0;
                if (document.getElementById("kpiArchivedDocs")) document.getElementById("kpiArchivedDocs").textContent = kpi.archived || 0;
                if (document.getElementById("docTotalCountBadge")) document.getElementById("docTotalCountBadge").textContent = `${kpi.total || 0} documents`;
            }
        },

        loadDocuments: async function () {
            const search = (document.getElementById("docSearchInput")?.value || "").trim();
            const typeFilter = document.getElementById("docTypeFilter")?.value || "All";
            const clientFilter = document.getElementById("docClientFilter")?.value || "All";
            const categoryFilter = document.getElementById("docCategoryFilter")?.value || "All";
            const ownerFilter = document.getElementById("docOwnerFilter")?.value || "All";

            const params = new URLSearchParams({
                action: 'list',
                page: currentDocPage,
                per_page: DOCS_PER_PAGE,
                kpi: currentKpiFilter
            });

            if (search) params.append('search', search);
            if (typeFilter && typeFilter !== 'All') params.append('type', typeFilter);
            if (clientFilter && clientFilter !== 'All') params.append('company_id', clientFilter);
            if (categoryFilter && categoryFilter !== 'All') params.append('category', categoryFilter);
            if (ownerFilter && ownerFilter !== 'All') params.append('owner_id', ownerFilter);

            const res = await apiRequest(`${API_URL}?${params.toString()}`);
            if (res && res.success && res.data) {
                documents = res.data.documents || [];
                totalItems = res.data.pagination?.total || 0;
                totalPages = res.data.pagination?.total_pages || 1;
                currentDocPage = res.data.pagination?.page || 1;
            } else {
                documents = [];
                totalItems = 0;
                totalPages = 1;
            }

            renderTableHeader();
            renderTableBody();
        },

        goToPage: function (p) {
            if (p < 1 || (totalPages > 0 && p > totalPages)) return;
            currentDocPage = p;
            this.loadDocuments();
        },

        onSearchInput: function (val) {
            currentDocPage = 1;
            const clearBtn = document.getElementById("docClearSearchBtn");
            if (clearBtn) clearBtn.style.display = val ? "block" : "none";

            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                this.loadDocuments();
            }, 300);
        },

        clearSearch: function () {
            currentDocPage = 1;
            const input = document.getElementById("docSearchInput");
            if (input) input.value = "";
            const clearBtn = document.getElementById("docClearSearchBtn");
            if (clearBtn) clearBtn.style.display = "none";
            this.loadDocuments();
        },

        onFilterChange: function () {
            currentDocPage = 1;
            this.loadDocuments();
        },

        filterByKPI: function (statusFilter) {
            currentKpiFilter = statusFilter || "All";
            currentDocPage = 1;

            if (statusFilter === "All") {
                const searchInput = document.getElementById("docSearchInput");
                if (searchInput) searchInput.value = "";
                const clearBtn = document.getElementById("docClearSearchBtn");
                if (clearBtn) clearBtn.style.display = "none";
            }

            this.highlightActiveKpiCard();
            this.loadDocuments();
        },

        highlightActiveKpiCard: function () {
            document.querySelectorAll(".documents-summary-card").forEach(card => {
                const kpi = card.getAttribute("data-kpi");
                if (currentKpiFilter && kpi === currentKpiFilter) {
                    card.classList.add("active");
                } else {
                    card.classList.remove("active");
                }
            });
        },

        toggleCustomizeColumnsMenu: function (e) {
            if (e) e.stopPropagation();
            const menu = document.getElementById("docColumnsDropdownMenu");
            if (!menu) return;
            const willShow = !menu.classList.contains("show");
            this.closeCustomizeColumnsMenu();
            if (willShow) {
                this.renderCustomizeColumnsList();
                menu.classList.add("show");
            }
        },

        closeCustomizeColumnsMenu: function () {
            const menu = document.getElementById("docColumnsDropdownMenu");
            if (menu) menu.classList.remove("show");
        },

        renderCustomizeColumnsList: function () {
            const container = document.getElementById("docColumnsList");
            if (!container) return;

            let html = "";
            columnOrder.forEach((colKey, index) => {
                const isChecked = columnVisibility[colKey];
                const label = columnLabels[colKey];
                const isFirst = index === 0;
                const isLast = index === columnOrder.length - 1;
                const isProtected = (colKey === "document");

                html += `
                    <div class="table-column-option ${isProtected ? 'is-protected' : ''}">
                        <span class="table-column-drag-handle" title="Reorder column">⋮⋮</span>
                        <input type="checkbox" ${isChecked ? 'checked' : ''} ${isProtected ? 'disabled' : ''} onchange="window.documentsApp.toggleColumnVisibility('${colKey}', this.checked)">
                        <span class="table-column-label-text">${escapeHtml(label)}</span>
                        ${isProtected ? '<span class="table-column-lock-icon" title="Protected column">🔒</span>' : `
                            <div class="table-column-move-btns">
                                <button type="button" class="table-column-move-btn" ${isFirst ? 'disabled' : ''} onclick="window.documentsApp.moveColumn('${colKey}', -1)" title="Move up">▲</button>
                                <button type="button" class="table-column-move-btn" ${isLast ? 'disabled' : ''} onclick="window.documentsApp.moveColumn('${colKey}', 1)" title="Move down">▼</button>
                            </div>
                        `}
                    </div>
                `;
            });

            container.innerHTML = html;
        },

        toggleColumnVisibility: function (colKey, isVisible) {
            columnVisibility[colKey] = isVisible;
            saveColumnsConfig();
            renderTableHeader();
            renderTableBody();
        },

        moveColumn: function (colKey, direction) {
            const idx = columnOrder.indexOf(colKey);
            if (idx < 0) return;
            const newIdx = idx + direction;
            if (newIdx < 0 || newIdx >= columnOrder.length) return;

            const temp = columnOrder[idx];
            columnOrder[idx] = columnOrder[newIdx];
            columnOrder[newIdx] = temp;

            saveColumnsConfig();
            this.renderCustomizeColumnsList();
            renderTableHeader();
            renderTableBody();
        },

        selectAllColumns: function () {
            Object.keys(columnVisibility).forEach(k => columnVisibility[k] = true);
            saveColumnsConfig();
            this.renderCustomizeColumnsList();
            renderTableHeader();
            renderTableBody();
        },

        hideOptionalColumns: function () {
            const optional = ['lastModified', 'fileSize', 'category', 'relatedRecord'];
            Object.keys(columnVisibility).forEach(k => {
                if (optional.includes(k)) columnVisibility[k] = false;
                else columnVisibility[k] = true;
            });
            saveColumnsConfig();
            this.renderCustomizeColumnsList();
            renderTableHeader();
            renderTableBody();
        },

        resetColumnsToDefault: function () {
            columnOrder = [...defaultColumnOrder];
            Object.keys(columnVisibility).forEach(k => columnVisibility[k] = true);
            saveColumnsConfig();
            this.renderCustomizeColumnsList();
            renderTableHeader();
            renderTableBody();
        },

        openViewDrawer: async function (id) {
            const res = await apiRequest(`${API_URL}?action=get&id=${id}`);
            if (!res || !res.success || !res.data || !res.data.document) {
                showAlert("Document Error", res?.message || "Unable to load document details.", "error");
                return;
            }

            const doc = res.data.document;
            activeDocForView = doc;

            const dvdTitle = document.getElementById("dvdTitle");
            if (dvdTitle) dvdTitle.textContent = doc.title;

            const dvdStatusBadge = document.getElementById("dvdStatusBadge");
            if (dvdStatusBadge) dvdStatusBadge.innerHTML = getStatusBadgeHtml(doc.status);

            const body = document.getElementById("docDrawerBody");
            if (body) {
                const clientName = doc.company_name || "—";
                const ownerName = doc.owner_name || "Unassigned";
                const uploaderName = doc.uploader_name || "—";
                const uploadDate = formatDate(doc.created_at);
                const modDate = formatDate(doc.updated_at);
                const displayFileName = doc.original_filename || doc.title;

                body.innerHTML = `
                    <div class="doc-preview-card">
                        <div class="doc-preview-badge file-type-${(doc.document_type || 'other').toLowerCase()}">
                            ${escapeHtml(doc.document_type || 'FILE')}
                        </div>
                        <div style="font-weight:700;font-size:15px;color:var(--text-heading);">${escapeHtml(displayFileName)}</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">${escapeHtml(doc.file_size || '0 KB')} • ${escapeHtml(uploadDate)}</div>
                    </div>

                    <div class="doc-info-card">
                        <div class="doc-info-title">File Specifications</div>
                        <div class="doc-info-row"><span style="color:var(--text-muted);">Document Code</span> <span style="font-weight:700;color:var(--primary);">${escapeHtml(doc.document_code)}</span></div>
                        <div class="doc-info-row"><span style="color:var(--text-muted);">File Type</span> <span style="font-weight:600;color:var(--text-heading);">${escapeHtml(doc.document_type || '—')}</span></div>
                        <div class="doc-info-row"><span style="color:var(--text-muted);">File Size</span> <span>${escapeHtml(doc.file_size || '—')}</span></div>
                        <div class="doc-info-row"><span style="color:var(--text-muted);">Uploaded By</span> <span style="font-weight:500;">${escapeHtml(uploaderName)}</span></div>
                        <div class="doc-info-row"><span style="color:var(--text-muted);">Uploaded Date</span> <span>${escapeHtml(uploadDate)}</span></div>
                        <div class="doc-info-row"><span style="color:var(--text-muted);">Last Modified</span> <span>${escapeHtml(modDate)}</span></div>
                    </div>

                    <div class="doc-info-card">
                        <div class="doc-info-title">CRM Context &amp; Assignment</div>
                        <div class="doc-info-row"><span style="color:var(--text-muted);">Client / Company</span> <span style="font-weight:600;color:var(--text-heading);">${escapeHtml(clientName)}</span></div>
                        <div class="doc-info-row"><span style="color:var(--text-muted);">Category</span> <span style="font-weight:500;">${escapeHtml(doc.category || 'General')}</span></div>
                        <div class="doc-info-row"><span style="color:var(--text-muted);">Related Record</span> <span style="font-weight:600;color:var(--primary);">${escapeHtml(doc.display_related_record || doc.related_record || '—')}</span></div>
                        <div class="doc-info-row"><span style="color:var(--text-muted);">Internal Owner</span> <span style="font-weight:500;">${escapeHtml(ownerName)}</span></div>
                        <div class="doc-info-row"><span style="color:var(--text-muted);">Access Status</span> <div>${getStatusBadgeHtml(doc.status)}</div></div>
                    </div>

                    ${doc.description ? `
                    <div class="doc-info-card">
                        <div class="doc-info-title">Description &amp; Notes</div>
                        <p style="font-size:12.5px;color:var(--text-body);margin:0;line-height:1.5;">${escapeHtml(doc.description)}</p>
                    </div>
                    ` : ''}

                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:12px;border-top:1px solid #E2E8F0;">
                        <button type="button" class="btn btn-secondary btn-xs" onclick="window.documentsApp.archiveDocument(${doc.id})">
                            ${doc.is_archived == 1 ? 'Restore Document' : 'Archive Document'}
                        </button>
                        <button type="button" class="btn btn-secondary btn-xs" style="color:#DC2626;border-color:#FCA5A5;" onclick="window.documentsApp.deleteDocument(${doc.id})">
                            Delete
                        </button>
                    </div>
                `;
            }

            const drawer = document.getElementById("docDetailsDrawer");
            if (drawer) drawer.classList.add("show");
            document.body.style.overflow = "hidden";
        },

        closeViewDrawer: function () {
            const drawer = document.getElementById("docDetailsDrawer");
            if (drawer) drawer.classList.remove("show");
            document.body.style.overflow = "";
        },

        openUploadDrawer: function () {
            const form = document.getElementById("docUploadForm");
            if (form) form.reset();

            const editDocId = document.getElementById("editDocId");
            if (editDocId) editDocId.value = "";

            const docModalTitle = document.getElementById("docModalTitle");
            if (docModalTitle) docModalTitle.textContent = "Upload Document";

            const docModalSubtitle = document.getElementById("docModalSubtitle");
            if (docModalSubtitle) docModalSubtitle.textContent = "Attach files and assign client, category, and CRM context.";

            const btnSaveDocSubmit = document.getElementById("btnSaveDocSubmit") || document.querySelector("#docUploadDrawer .btn-primary");
            if (btnSaveDocSubmit) btnSaveDocSubmit.textContent = "Upload Document";

            const fieldDocTitle = document.getElementById("fieldDocTitle");
            if (fieldDocTitle) fieldDocTitle.value = "";
            const fieldDocFile = document.getElementById("fieldDocFile");
            if (fieldDocFile) fieldDocFile.value = "";
            const fieldDocDesc = document.getElementById("fieldDocDesc");
            if (fieldDocDesc) fieldDocDesc.value = "";

            const fieldDocType = document.getElementById("fieldDocType");
            if (fieldDocType) fieldDocType.value = "PDF";
            const fieldDocClient = document.getElementById("fieldDocClient");
            if (fieldDocClient) fieldDocClient.value = "";
            const fieldDocCategory = document.getElementById("fieldDocCategory");
            if (fieldDocCategory) fieldDocCategory.value = "Proposals";
            const fieldDocStatus = document.getElementById("fieldDocStatus");
            if (fieldDocStatus) fieldDocStatus.value = "Available";

            this.loadRelatedOptions();

            const drawer = document.getElementById("docUploadDrawer");
            if (drawer) drawer.classList.add("show");
            document.body.style.overflow = "hidden";
        },

        openEditDrawer: async function (id) {
            const targetId = id || activeDocForView?.id;
            if (!targetId) return;

            let doc = activeDocForView && String(activeDocForView.id) === String(targetId) ? activeDocForView : null;
            if (!doc) {
                const res = await apiRequest(`${API_URL}?action=get&id=${targetId}`);
                if (!res || !res.success || !res.data?.document) {
                    showAlert("Document Error", res?.message || "Unable to load document details.", "error");
                    return;
                }
                doc = res.data.document;
            }

            this.closeViewDrawer();

            const form = document.getElementById("docUploadForm");
            if (form) form.reset();

            const editDocId = document.getElementById("editDocId");
            if (editDocId) editDocId.value = doc.id;

            const docModalTitle = document.getElementById("docModalTitle");
            if (docModalTitle) docModalTitle.textContent = `Edit Document (${doc.document_code || 'DOC'})`;

            const docModalSubtitle = document.getElementById("docModalSubtitle");
            if (docModalSubtitle) docModalSubtitle.textContent = "Modify document details, metadata, and linked CRM associations.";

            const btnSaveDocSubmit = document.getElementById("btnSaveDocSubmit") || document.querySelector("#docUploadDrawer .btn-primary");
            if (btnSaveDocSubmit) btnSaveDocSubmit.textContent = "Update Document";

            const fieldDocTitle = document.getElementById("fieldDocTitle");
            if (fieldDocTitle) fieldDocTitle.value = doc.title || "";

            const fieldDocFile = document.getElementById("fieldDocFile");
            if (fieldDocFile) fieldDocFile.value = "";

            const fieldDocType = document.getElementById("fieldDocType");
            if (fieldDocType) fieldDocType.value = doc.document_type || "PDF";

            const fieldDocClient = document.getElementById("fieldDocClient");
            if (fieldDocClient) fieldDocClient.value = doc.company_id || "";

            const fieldDocCategory = document.getElementById("fieldDocCategory");
            if (fieldDocCategory) fieldDocCategory.value = doc.category || "Proposals";

            const fieldDocOwner = document.getElementById("fieldDocOwner");
            if (fieldDocOwner) fieldDocOwner.value = doc.owner_id || "";

            const fieldDocStatus = document.getElementById("fieldDocStatus");
            if (fieldDocStatus) fieldDocStatus.value = doc.status || "Available";

            const fieldDocDesc = document.getElementById("fieldDocDesc");
            if (fieldDocDesc) fieldDocDesc.value = doc.description || "";

            const relId = doc.related_id || doc.project_id || doc.deal_id || doc.proposal_id || doc.contract_id || doc.invoice_id || null;
            const relType = doc.related_type || (doc.project_id ? 'project' : (doc.deal_id ? 'deal' : (doc.proposal_id ? 'proposal' : (doc.contract_id ? 'contract' : (doc.invoice_id ? 'invoice' : '')))));

            await this.loadRelatedOptions(relId, relType);

            const drawer = document.getElementById("docUploadDrawer");
            if (drawer) drawer.classList.add("show");
            document.body.style.overflow = "hidden";
        },

        closeUploadDrawer: function () {
            const drawer = document.getElementById("docUploadDrawer");
            if (drawer) drawer.classList.remove("show");
            document.body.style.overflow = "";
        },

        saveDocumentSubmit: async function () {
            const editIdInput = document.getElementById("editDocId");
            const isEdit = Boolean(editIdInput && editIdInput.value);
            const docId = isEdit ? parseInt(editIdInput.value, 10) : null;

            const titleInput = document.getElementById("fieldDocTitle");
            const clientInput = document.getElementById("fieldDocClient");
            const typeInput = document.getElementById("fieldDocType");
            const categoryInput = document.getElementById("fieldDocCategory");
            const relatedInput = document.getElementById("fieldDocRelated");
            const ownerInput = document.getElementById("fieldDocOwner");
            const statusInput = document.getElementById("fieldDocStatus");
            const descInput = document.getElementById("fieldDocDesc");
            const fileInput = document.getElementById("fieldDocFile");

            const title = (titleInput?.value || "").trim();
            const clientId = (clientInput?.value || "").trim();
            const fileType = (typeInput?.value || "").trim();
            const category = (categoryInput?.value || "").trim();
            const ownerId = (ownerInput?.value || "").trim();
            const status = (statusInput?.value || "").trim();
            const desc = (descInput?.value || "").trim();

            const relId = relatedInput?.value || "";
            const relOption = relatedInput && relatedInput.selectedOptions ? relatedInput.selectedOptions[0] : null;
            const relType = relOption ? (relOption.getAttribute("data-type") || "") : "";

            if (!title) {
                showAlert("Validation Required", "Please provide a Document Name.", "warning");
                return;
            }
            if (!status) {
                showAlert("Validation Required", "Please select a Status.", "warning");
                return;
            }

            const formData = new FormData();
            formData.append('action', isEdit ? 'update' : 'upload');
            if (isEdit) {
                formData.append('id', docId);
            }
            formData.append('title', title);
            formData.append('status', status);
            if (fileType) formData.append('document_type', fileType);
            if (category) formData.append('category', category);
            if (clientId) formData.append('company_id', clientId);
            if (ownerId) formData.append('owner_id', ownerId);
            if (desc) formData.append('description', desc);

            if (relId) {
                formData.append('related_id', relId);
                formData.append('related_type', relType);
                if (relType === 'project') formData.append('project_id', relId);
                else if (relType === 'deal') formData.append('deal_id', relId);
                else if (relType === 'proposal') formData.append('proposal_id', relId);
                else if (relType === 'contract') formData.append('contract_id', relId);
                else if (relType === 'invoice') formData.append('invoice_id', relId);
            } else {
                formData.append('related_id', '');
                formData.append('related_type', '');
            }

            if (fileInput && fileInput.files && fileInput.files[0]) {
                formData.append('file', fileInput.files[0]);
            }

            const submitBtn = document.getElementById("btnSaveDocSubmit") || document.querySelector("#docUploadDrawer .btn-primary");
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = isEdit ? "Updating..." : "Uploading...";
            }

            try {
                const resp = await fetch(API_URL, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' }
                });
                const res = await resp.json();

                if (res && res.success) {
                    this.closeUploadDrawer();
                    await this.loadSummary();
                    await this.loadDocuments();
                    const verb = isEdit ? 'updated' : 'uploaded';
                    this.showToast(`Document '${res.data?.document_code || title}' ${verb} successfully!`);
                } else {
                    showAlert(isEdit ? "Update Failed" : "Upload Failed", res?.message || "Unable to save document.", "error");
                }
            } catch (err) {
                console.error("Save Document Error:", err);
                showAlert("Error", "Failed to communicate with the server.", "error");
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = isEdit ? "Update Document" : "Upload Document";
                }
            }
        },

        triggerPreview: function () {
            if (!activeDocForView) return;
            if (!activeDocForView.file_path) {
                this.showToast("No file attached to this document.");
                return;
            }
            window.open(`${API_URL}?action=preview&id=${activeDocForView.id}`, '_blank');
        },

        triggerDownload: function () {
            if (!activeDocForView) return;
            if (!activeDocForView.file_path) {
                this.showToast("No file attached to this document.");
                return;
            }
            window.location.href = `${API_URL}?action=download&id=${activeDocForView.id}`;
        },

        triggerShare: async function () {
            if (!activeDocForView) return;
            const res = await apiRequest(`${API_URL}?action=share`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: activeDocForView.id })
            });

            if (res && res.success) {
                activeDocForView.status = 'Shared';
                const dvdStatusBadge = document.getElementById("dvdStatusBadge");
                if (dvdStatusBadge) dvdStatusBadge.innerHTML = getStatusBadgeHtml('Shared');
                await this.loadSummary();
                await this.loadDocuments();
                this.showToast(`Document '${activeDocForView.document_code || activeDocForView.title}' status set to Shared.`);
            } else {
                showAlert("Share Failed", res?.message || "Could not share document.", "error");
            }
        },

        archiveDocument: async function (id) {
            const targetId = id || activeDocForView?.id;
            if (!targetId) return;

            const res = await apiRequest(`${API_URL}?action=archive`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: targetId })
            });

            if (res && res.success) {
                this.closeViewDrawer();
                await this.loadSummary();
                await this.loadDocuments();
                this.showToast(res.message || "Document archive state updated.");
            } else {
                showAlert("Archive Failed", res?.message || "Could not update document archive state.", "error");
            }
        },

        deleteDocument: async function (id) {
            const targetId = id || activeDocForView?.id;
            if (!targetId) return;

            if (!confirm("Are you sure you want to permanently delete this document and its uploaded file?")) {
                return;
            }

            const res = await apiRequest(`${API_URL}?action=delete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: targetId })
            });

            if (res && res.success) {
                this.closeViewDrawer();
                await this.loadSummary();
                await this.loadDocuments();
                this.showToast(res.message || "Document deleted successfully.");
            } else {
                showAlert("Delete Failed", res?.message || "Could not delete document.", "error");
            }
        },

        exportCSV: function () {
            window.location.href = `${API_URL}?action=export_csv`;
        },

        showToast: function (msg) {
            const toast = document.createElement("div");
            toast.style.cssText = "position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background-color:#101828;color:#ffffff;padding:10px 18px;border-radius:999px;font-size:13px;font-weight:500;box-shadow:0 10px 15px rgba(0,0,0,0.2);z-index:2000;pointer-events:none;transition:opacity 0.3s ease;";
            toast.textContent = msg;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = "0";
                setTimeout(() => toast.remove(), 300);
            }, 2500);
        }
    };

    document.addEventListener("click", function (e) {
        const wrapper = document.querySelector(".table-columns-dropdown-wrapper");
        if (wrapper && !wrapper.contains(e.target)) {
            window.documentsApp.closeCustomizeColumnsMenu();
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            window.documentsApp.closeCustomizeColumnsMenu();
            window.documentsApp.closeViewDrawer();
            window.documentsApp.closeUploadDrawer();
        }
    });

    document.addEventListener("DOMContentLoaded", function () {
        window.documentsApp.init();
    });
})();
