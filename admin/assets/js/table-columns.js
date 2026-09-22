/**
 * NexFlow CRM — Table Column Management System
 * Hide/Show Columns, Drag-and-Drop Reordering,
 * & Intrinsic Content-Based Sizing (width: max-content)
 */
(function () {
  'use strict';

  const STORAGE_KEY = 'NexFlow_table_column_preferences_v2';
  const OBSOLETE_KEY = 'NexFlow_table_columns_v1';

  // SVG Icons
  const GRIP_ICON_SVG = '<svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>';
  const LOCK_ICON_SVG = '<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>';
  const COLUMNS_ICON_SVG = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>';

  let preferencesStore = {};

  // Clean up obsolete key if present
  try {
    localStorage.removeItem(OBSOLETE_KEY);
  } catch (e) {}

  function loadPreferences() {
    try {
      preferencesStore = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    } catch (e) {
      preferencesStore = {};
    }
  }

  function savePreferences() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(preferencesStore));
    } catch (e) {
      console.warn('Unable to save table column preferences to localStorage');
    }
  }

  function getTableId(table) {
    if (!table) return null;
    return table.id || table.getAttribute('data-table-id') || null;
  }

  function getColumnId(th, index) {
    if (th.dataset.columnId) return th.dataset.columnId;
    const text = th.textContent.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
    return text ? text : `col_${index}`;
  }

  function isProtected(th) {
    if (th.hasAttribute('data-protected') && th.getAttribute('data-protected') !== 'false') return true;
    if (th.dataset.protected === 'true') return true;

    const text = th.textContent.trim().toLowerCase();
    const hasCheckbox = th.querySelector('input[type="checkbox"], .custom-checkbox');
    if (hasCheckbox) return true;
    if (text === 'actions' || text === 'action' || th.style.textAlign === 'right' || th.style.textAlign === 'center') {
      if (text.includes('action')) return true;
    }
    return false;
  }

  function getColumnLabel(th) {
    return th.textContent.trim() || 'Column';
  }

  const registeredTables = new Map();

  class TableColumnManager {
    constructor(table) {
      this.table = table;
      this.tableId = getTableId(table);
      if (!this.tableId) {
        this.tableId = 'table_' + Math.random().toString(36).substr(2, 9);
        this.table.id = this.tableId;
      }

      this.originalColumns = [];
      this.currentColumns = [];
      this.observer = null;
      this.dropdownEl = null;

      this.init();
    }

    init() {
      if (this.table.dataset.columnManager === 'disabled' || this.table.getAttribute('data-column-manager') === 'disabled') return;
      if (this.table.dataset.columnsManagerInitialized === 'true') return;
      this.table.dataset.columnsManagerInitialized = 'true';

      loadPreferences();
      if (!preferencesStore[this.tableId]) {
        preferencesStore[this.tableId] = { order: [], hidden: [] };
      }

      this.parseHeader();
      this.applySavedPreferences();
      this.setupToolbarDropdown();
      this.setupMutationObserver();
    }

    parseHeader() {
      const thead = this.table.querySelector('thead');
      if (!thead) return;

      const thList = Array.from(thead.querySelectorAll('tr:last-child th'));
      this.originalColumns = thList.map((th, index) => {
        const id = getColumnId(th, index);
        const protectedCol = isProtected(th);
        const label = getColumnLabel(th);

        th.dataset.columnId = id;
        th.dataset.origIndex = String(index);
        if (protectedCol) {
          th.setAttribute('data-protected', 'true');
        }

        return {
          id: id,
          origIndex: index,
          th: th,
          label: label,
          protected: protectedCol
        };
      });

      this.currentColumns = [...this.originalColumns];
    }

    applySavedPreferences() {
      const pref = preferencesStore[this.tableId] || { order: [], hidden: [] };

      // Apply reordering if stored order is valid
      if (Array.isArray(pref.order) && pref.order.length > 0) {
        const colMap = new Map(this.originalColumns.map(c => [c.id, c]));
        const ordered = [];

        pref.order.forEach(id => {
          if (colMap.has(id)) {
            ordered.push(colMap.get(id));
            colMap.delete(id);
          }
        });

        colMap.forEach(col => ordered.push(col));

        // Keep protected boundary columns intact
        const checkboxCol = ordered.find(c => c.id === 'checkbox');
        if (checkboxCol) {
          const idx = ordered.indexOf(checkboxCol);
          if (idx > 0) {
            ordered.splice(idx, 1);
            ordered.unshift(checkboxCol);
          }
        }
        const actionsCol = ordered.find(c => c.id === 'actions' || c.id === 'action');
        if (actionsCol) {
          const idx = ordered.indexOf(actionsCol);
          if (idx >= 0 && idx < ordered.length - 1) {
            ordered.splice(idx, 1);
            ordered.push(actionsCol);
          }
        }

        this.currentColumns = ordered;
      }

      this.applyDomOrderAndVisibility();
    }

    applyDomOrderAndVisibility() {
      const theadTr = this.table.querySelector('thead tr:last-child');
      if (!theadTr) return;

      const pref = preferencesStore[this.tableId] || { order: [], hidden: [] };
      const hiddenSet = new Set(Array.isArray(pref.hidden) ? pref.hidden : []);

      // 1. Reorder <th> elements in thead
      this.currentColumns.forEach(col => {
        theadTr.appendChild(col.th);

        const isHidden = !col.protected && hiddenSet.has(col.id);
        if (isHidden) {
          col.th.classList.add('is-column-hidden');
        } else {
          col.th.classList.remove('is-column-hidden');
        }
      });

      // 2. Reorder & hide <td> cells across all tbody rows
      this.applyToTbodyRows();

      // 3. Recalculate table dynamic sizing & stretch
      this.recalculateTableSizing();
    }

    applyToTbodyRows() {
      const pref = preferencesStore[this.tableId] || { order: [], hidden: [] };
      const hiddenSet = new Set(Array.isArray(pref.hidden) ? pref.hidden : []);
      const visibleCount = this.currentColumns.filter(c => c.protected || !hiddenSet.has(c.id)).length;

      const tbodies = this.table.querySelectorAll('tbody');
      tbodies.forEach(tbody => {
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(row => {
          const cells = Array.from(row.children);
          if (cells.length === 0) return;

          // Handle single full-width row (e.g. empty state / loading indicator)
          if (cells.length === 1 && (cells[0].hasAttribute('colspan') || cells[0].classList.contains('empty-state') || cells[0].classList.contains('no-records'))) {
            cells[0].setAttribute('colspan', String(visibleCount));
            return;
          }

          const cellByColId = new Map();
          const hasColDataIds = cells.some(cell => cell.dataset && cell.dataset.columnId);

          if (hasColDataIds) {
            cells.forEach(cell => {
              if (cell.dataset && cell.dataset.columnId) {
                cellByColId.set(cell.dataset.columnId, cell);
              }
            });
          } else {
            if (cells.length === this.originalColumns.length) {
              this.originalColumns.forEach((col, idx) => {
                const cell = cells[idx];
                if (cell) {
                  cell.dataset.columnId = col.id;
                  cellByColId.set(col.id, cell);
                }
              });
            } else {
              this.originalColumns.forEach((col) => {
                const origThIdx = this.originalColumns.findIndex(c => c.id === col.id);
                if (origThIdx >= 0 && cells[origThIdx]) {
                  cells[origThIdx].dataset.columnId = col.id;
                  cellByColId.set(col.id, cells[origThIdx]);
                }
              });
            }
          }

          this.currentColumns.forEach(col => {
            const cell = cellByColId.get(col.id);
            if (cell) {
              row.appendChild(cell);

              const isHidden = !col.protected && hiddenSet.has(col.id);
              if (isHidden) {
                cell.classList.add('is-column-hidden');
              } else {
                cell.classList.remove('is-column-hidden');
              }
            }
          });
        });
      });
    }

    recalculateTableSizing() {
      const pref = preferencesStore[this.tableId] || { order: [], hidden: [] };
      const hiddenSet = new Set(Array.isArray(pref.hidden) ? pref.hidden : []);

      let totalVisibleMinWidth = 0;
      this.currentColumns.forEach(col => {
        const isHidden = !col.protected && hiddenSet.has(col.id);
        if (!isHidden) {
          totalVisibleMinWidth += (col.minWidth || 130);
        }
      });

      const wrapper = this.table.closest('.crm-table-wrapper, .companies-table-wrapper, .team-table-wrapper, .settings-table-wrapper, .contacts-table-wrapper') || this.table.parentElement;

      if (!wrapper) return;

      const wrapperWidth = wrapper.clientWidth;

      if (wrapperWidth > 0 && totalVisibleMinWidth > wrapperWidth) {
        // Columns overflow: set explicit pixel width so the wrapper can scroll
        this.table.style.width = totalVisibleMinWidth + 'px';
        this.table.style.minWidth = totalVisibleMinWidth + 'px';
      } else {
        // Columns fit: use natural content width (no stretching, no giant gaps).
        // 'max-content' lets the table be only as wide as its content needs.
        this.table.style.width = 'max-content';
        this.table.style.minWidth = '0';
      }

      // Clamp scroll position on wrapper if wrapper scroll width shrank
      const maxScroll = Math.max(0, wrapper.scrollWidth - wrapper.clientWidth);
      if (wrapper.scrollLeft > maxScroll) {
        wrapper.scrollLeft = maxScroll;
      }
    }

    toggleColumnVisibility(colId, isVisible) {
      const col = this.currentColumns.find(c => c.id === colId);
      if (!col || col.protected) return;

      if (!preferencesStore[this.tableId]) {
        preferencesStore[this.tableId] = { order: [], hidden: [] };
      }
      let hiddenArr = preferencesStore[this.tableId].hidden || [];

      if (isVisible) {
        hiddenArr = hiddenArr.filter(id => id !== colId);
      } else {
        if (!hiddenArr.includes(colId)) {
          hiddenArr.push(colId);
        }
      }

      preferencesStore[this.tableId].hidden = hiddenArr;
      savePreferences();

      this.applyDomOrderAndVisibility();
      this.updateDropdownUI();
    }

    reorderColumns(fromColId, toColId, position = 'before') {
      const fromIdx = this.currentColumns.findIndex(c => c.id === fromColId);
      const toIdx = this.currentColumns.findIndex(c => c.id === toColId);

      if (fromIdx < 0 || toIdx < 0 || fromIdx === toIdx) return;

      const item = this.currentColumns[fromIdx];
      if (item.protected) return;

      this.currentColumns.splice(fromIdx, 1);

      let targetIdx = this.currentColumns.findIndex(c => c.id === toColId);
      if (position === 'after') {
        targetIdx += 1;
      }

      this.currentColumns.splice(targetIdx, 0, item);

      // Keep protected boundary columns intact
      const checkboxCol = this.currentColumns.find(c => c.id === 'checkbox');
      if (checkboxCol) {
        const idx = this.currentColumns.indexOf(checkboxCol);
        if (idx > 0) {
          this.currentColumns.splice(idx, 1);
          this.currentColumns.unshift(checkboxCol);
        }
      }
      const actionsCol = this.currentColumns.find(c => c.id === 'actions' || c.id === 'action');
      if (actionsCol) {
        const idx = this.currentColumns.indexOf(actionsCol);
        if (idx >= 0 && idx < this.currentColumns.length - 1) {
          this.currentColumns.splice(idx, 1);
          this.currentColumns.push(actionsCol);
        }
      }

      if (!preferencesStore[this.tableId]) {
        preferencesStore[this.tableId] = { order: [], hidden: [] };
      }
      preferencesStore[this.tableId].order = this.currentColumns.map(c => c.id);
      savePreferences();

      this.applyDomOrderAndVisibility();
      this.updateDropdownUI();
    }

    showAll() {
      if (!preferencesStore[this.tableId]) {
        preferencesStore[this.tableId] = { order: [], hidden: [] };
      }
      preferencesStore[this.tableId].hidden = [];
      savePreferences();

      this.applyDomOrderAndVisibility();
      this.updateDropdownUI();
    }

    hideOptional() {
      if (!preferencesStore[this.tableId]) {
        preferencesStore[this.tableId] = { order: [], hidden: [] };
      }

      const nonProtectedCols = this.currentColumns.filter(c => !c.protected);
      const optionalToHide = nonProtectedCols.slice(1).map(c => c.id);

      preferencesStore[this.tableId].hidden = optionalToHide;
      savePreferences();

      this.applyDomOrderAndVisibility();
      this.updateDropdownUI();
    }

    resetTable() {
      delete preferencesStore[this.tableId];
      savePreferences();

      this.currentColumns = [...this.originalColumns];
      this.applyDomOrderAndVisibility();
      this.updateDropdownUI();
    }

    setupMutationObserver() {
      const tbody = this.table.querySelector('tbody');
      if (!tbody) return;

      this.observer = new MutationObserver(() => {
        this.applyToTbodyRows();
        this.recalculateTableSizing();
      });

      this.observer.observe(tbody, { childList: true, subtree: false });
    }

    attachDropdownEvents(dropdownWrapper) {
      const toggleBtn = dropdownWrapper.querySelector('.table-columns-btn');
      const menu = dropdownWrapper.querySelector('.table-columns-dropdown-menu');
      if (!toggleBtn || !menu) return;

      toggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isShown = menu.classList.contains('show');
        document.querySelectorAll('.table-columns-dropdown-menu.show').forEach(m => m.classList.remove('show'));
        if (!isShown) {
          menu.classList.add('show');
          toggleBtn.setAttribute('aria-expanded', 'true');
        } else {
          toggleBtn.setAttribute('aria-expanded', 'false');
        }
      });

      document.addEventListener('click', (e) => {
        if (!dropdownWrapper.contains(e.target)) {
          menu.classList.remove('show');
          toggleBtn.setAttribute('aria-expanded', 'false');
        }
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && menu.classList.contains('show')) {
          menu.classList.remove('show');
          toggleBtn.setAttribute('aria-expanded', 'false');
          toggleBtn.focus();
        }
      });

      const showAllBtn = dropdownWrapper.querySelector('.btn-show-all');
      if (showAllBtn) showAllBtn.addEventListener('click', (e) => { e.stopPropagation(); this.showAll(); });

      const hideOptBtn = dropdownWrapper.querySelector('.btn-hide-optional');
      if (hideOptBtn) hideOptBtn.addEventListener('click', (e) => { e.stopPropagation(); this.hideOptional(); });

      const resetBtn = dropdownWrapper.querySelector('.btn-reset');
      if (resetBtn) resetBtn.addEventListener('click', (e) => { e.stopPropagation(); this.resetTable(); });
    }

    setupToolbarDropdown() {
      const container = this.table.closest('.page-container, .contacts-page, .crm-table-card, .companies-table-card, .team-card, .reports-card') ||
                        this.table.closest('.card') ||
                        this.table.parentElement;

      if (!container) return;

      let existingWrapper = document.querySelector(`[data-table-dropdown-for="${this.tableId}"]`) ||
                            container.querySelector('.projects-toolbar .table-columns-dropdown-wrapper, .table-columns-dropdown-wrapper');
      if (existingWrapper) {
        existingWrapper.setAttribute('data-table-dropdown-for', this.tableId);
        if (!existingWrapper.querySelector('.table-columns-dropdown-menu')) {
          existingWrapper.innerHTML = `
            <button type="button" class="btn btn-secondary btn-sm table-columns-btn" id="btnCustomizeColumns" title="Show, hide, and reorder table columns" aria-label="Show, hide, and reorder table columns" aria-haspopup="menu">
              ${COLUMNS_ICON_SVG}
              <span>Customize Columns</span>
            </button>
            <div class="table-columns-dropdown-menu" role="menu">
              <div class="table-columns-dropdown-header">Reorder &amp; Visibility</div>
              <div class="table-columns-list"></div>
              <div class="table-columns-footer">
                <button type="button" class="table-columns-footer-btn btn-show-all">Show All</button>
                <button type="button" class="table-columns-footer-btn btn-hide-optional">Hide Optional</button>
                <button type="button" class="table-columns-footer-btn btn-reset">Reset</button>
              </div>
            </div>
          `;
          this.attachDropdownEvents(existingWrapper);
        }
        this.dropdownEl = existingWrapper;
        this.updateDropdownUI();
        return;
      }

      let targetToolbar = container.querySelector('.projects-toolbar, .contacts-toolbar-right, .toolbar-actions, .tasks-controls-right, .leads-controls-right, .companies-controls-right, .team-header-actions, .reports-card-header, .card-header');

      const dropdownWrapper = document.createElement('div');
      dropdownWrapper.className = 'table-columns-dropdown-wrapper';
      dropdownWrapper.setAttribute('data-table-dropdown-for', this.tableId);

      dropdownWrapper.innerHTML = `
        <button type="button" class="btn btn-secondary btn-sm table-columns-btn" id="btnCustomizeColumns" title="Show, hide, and reorder table columns" aria-label="Show, hide, and reorder table columns" aria-haspopup="menu">
          ${COLUMNS_ICON_SVG}
          <span>Customize Columns</span>
        </button>
        <div class="table-columns-dropdown-menu" role="menu">
          <div class="table-columns-dropdown-header">Reorder &amp; Visibility</div>
          <div class="table-columns-list"></div>
          <div class="table-columns-footer">
            <button type="button" class="table-columns-footer-btn btn-show-all">Show All</button>
            <button type="button" class="table-columns-footer-btn btn-hide-optional">Hide Optional</button>
            <button type="button" class="table-columns-footer-btn btn-reset">Reset</button>
          </div>
        </div>
      `;

      if (targetToolbar) {
        targetToolbar.appendChild(dropdownWrapper);
      } else {
        const wrapper = this.table.closest('.crm-table-wrapper, .companies-table-wrapper, .team-table-wrapper') || this.table;
        const topBar = document.createElement('div');
        topBar.style.cssText = 'display: flex; justify-content: flex-end; margin-bottom: 8px;';
        topBar.appendChild(dropdownWrapper);
        if (wrapper && wrapper.parentNode) {
          wrapper.parentNode.insertBefore(topBar, wrapper);
        }
      }

      this.dropdownEl = dropdownWrapper;
      this.attachDropdownEvents(dropdownWrapper);
      this.updateDropdownUI();
    }

    updateDropdownUI() {
      if (!this.dropdownEl) return;
      const listContainer = this.dropdownEl.querySelector('.table-columns-list');
      if (!listContainer) return;

      const pref = preferencesStore[this.tableId] || { order: [], hidden: [] };
      const hiddenSet = new Set(Array.isArray(pref.hidden) ? pref.hidden : []);

      listContainer.innerHTML = this.currentColumns.map(col => {
        const isVisible = !col.protected && !hiddenSet.has(col.id);
        const isProt = col.protected;

        return `
          <div class="table-column-option ${isProt ? 'is-protected' : ''}" 
               data-col-id="${col.id}" 
               draggable="${isProt ? 'false' : 'true'}"
               tabindex="${isProt ? '-1' : '0'}"
               aria-label="Reorder ${escapeHtml(col.label)}">
            <span class="table-column-drag-handle" title="${isProt ? 'Locked position' : 'Drag to reorder'}">
              ${GRIP_ICON_SVG}
            </span>
            <input type="checkbox" data-col-id="${col.id}" ${isVisible || isProt ? 'checked' : ''} ${isProt ? 'disabled' : ''} aria-label="Toggle ${escapeHtml(col.label)} visibility">
            <span class="table-column-label-text">${escapeHtml(col.label)}</span>
            ${isProt ? `<span class="table-column-lock-icon" title="Required protected column">${LOCK_ICON_SVG}</span>` : `
              <div class="table-column-move-btns">
                <button type="button" class="table-column-move-btn btn-move-up" title="Move Up" aria-label="Move ${escapeHtml(col.label)} Up">▲</button>
                <button type="button" class="table-column-move-btn btn-move-down" title="Move Down" aria-label="Move ${escapeHtml(col.label)} Down">▼</button>
              </div>
            `}
          </div>
        `;
      }).join('');

      listContainer.querySelectorAll('input[type="checkbox"]:not([disabled])').forEach(cb => {
        cb.addEventListener('change', (e) => {
          e.stopPropagation();
          const colId = cb.dataset.colId;
          this.toggleColumnVisibility(colId, cb.checked);
        });
      });

      listContainer.querySelectorAll('.btn-move-up').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const itemEl = btn.closest('.table-column-option');
          const colId = itemEl.dataset.colId;
          const idx = this.currentColumns.findIndex(c => c.id === colId);
          if (idx > 1) {
            const prevColId = this.currentColumns[idx - 1].id;
            this.reorderColumns(colId, prevColId, 'before');
          }
        });
      });

      listContainer.querySelectorAll('.btn-move-down').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const itemEl = btn.closest('.table-column-option');
          const colId = itemEl.dataset.colId;
          const idx = this.currentColumns.findIndex(c => c.id === colId);
          if (idx < this.currentColumns.length - 2) {
            const nextColId = this.currentColumns[idx + 1].id;
            this.reorderColumns(colId, nextColId, 'after');
          }
        });
      });

      this.attachDragAndDrop(listContainer);
    }

    attachDragAndDrop(listContainer) {
      let draggedEl = null;

      listContainer.querySelectorAll('.table-column-option:not(.is-protected)').forEach(item => {
        item.addEventListener('dragstart', (e) => {
          draggedEl = item;
          item.classList.add('is-dragging');
          e.dataTransfer.effectAllowed = 'move';
          e.dataTransfer.setData('text/plain', item.dataset.colId);
        });

        item.addEventListener('dragover', (e) => {
          e.preventDefault();
          if (!draggedEl || draggedEl === item || item.classList.contains('is-protected')) return;
          e.dataTransfer.dropEffect = 'move';

          const rect = item.getBoundingClientRect();
          const midY = rect.top + rect.height / 2;
          
          listContainer.querySelectorAll('.table-column-option').forEach(el => {
            el.classList.remove('drop-target-above', 'drop-target-below');
          });

          if (e.clientY < midY) {
            item.classList.add('drop-target-above');
          } else {
            item.classList.add('drop-target-below');
          }
        });

        item.addEventListener('dragleave', () => {
          item.classList.remove('drop-target-above', 'drop-target-below');
        });

        item.addEventListener('drop', (e) => {
          e.preventDefault();
          e.stopPropagation();

          listContainer.querySelectorAll('.table-column-option').forEach(el => {
            el.classList.remove('drop-target-above', 'drop-target-below');
          });

          if (!draggedEl || draggedEl === item || item.classList.contains('is-protected')) return;

          const fromColId = draggedEl.dataset.colId;
          const toColId = item.dataset.colId;

          const rect = item.getBoundingClientRect();
          const midY = rect.top + rect.height / 2;
          const position = (e.clientY < midY) ? 'before' : 'after';

          this.reorderColumns(fromColId, toColId, position);
        });

        item.addEventListener('dragend', () => {
          if (draggedEl) {
            draggedEl.classList.remove('is-dragging');
            draggedEl = null;
          }
          listContainer.querySelectorAll('.table-column-option').forEach(el => {
            el.classList.remove('drop-target-above', 'drop-target-below');
          });
        });
      });
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[m]));
  }

  // Global Public API
  window.NexFlowTableColumns = {
    init: function () {
      loadPreferences();
      const tables = document.querySelectorAll('table.crm-table, table.companies-table, table.team-table, table.reports-table, table.reports-mini-table');
      tables.forEach(table => {
        if (table.dataset.columnManager === 'disabled' || table.getAttribute('data-column-manager') === 'disabled') {
          return;
        }
        if (!registeredTables.has(table)) {
          registeredTables.set(table, new TableColumnManager(table));
        }
      });
    },

    registerTable: function (tableOrId) {
      const table = (typeof tableOrId === 'string') ? document.getElementById(tableOrId) : tableOrId;
      if (table && (table.dataset.columnManager === 'disabled' || table.getAttribute('data-column-manager') === 'disabled')) {
        return null;
      }
      if (table && !registeredTables.has(table)) {
        registeredTables.set(table, new TableColumnManager(table));
      }
      return registeredTables.get(table);
    },

    toggleColumnVisibility: function (tableId, colId, isVisible) {
      const manager = this.getManager(tableId);
      if (manager) manager.toggleColumnVisibility(colId, isVisible);
    },

    reorderColumns: function (tableId, fromColId, toColId, position) {
      const manager = this.getManager(tableId);
      if (manager) manager.reorderColumns(fromColId, toColId, position);
    },

    showAll: function (tableId) {
      const manager = this.getManager(tableId);
      if (manager) manager.showAll();
    },

    hideOptional: function (tableId) {
      const manager = this.getManager(tableId);
      if (manager) manager.hideOptional();
    },

    resetTable: function (tableId) {
      const manager = this.getManager(tableId);
      if (manager) manager.resetTable();
    },

    reapply: function (tableId) {
      const manager = this.getManager(tableId);
      if (manager) {
        manager.applyToTbodyRows();
        manager.recalculateTableSizing();
      }
    },

    recalculateSizing: function (tableId) {
      const manager = this.getManager(tableId);
      if (manager) manager.recalculateTableSizing();
    },

    getManager: function (tableId) {
      for (const [table, manager] of registeredTables.entries()) {
        if (manager.tableId === tableId || table.id === tableId) {
          return manager;
        }
      }
      return null;
    }
  };

  // Auto-init on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.NexFlowTableColumns.init());
  } else {
    window.NexFlowTableColumns.init();
  }

})();

