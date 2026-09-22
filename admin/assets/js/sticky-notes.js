/**
 * NexFlow CRM — Global Sticky Notes Module JavaScript
 * Versioned LocalStorage persistence, multi-scope (Global / Current Page),
 * filtering, searching, sorting, pinning, archiving, recoloring, duplicating,
 * deletion confirmation, and draggable floating cards (max 3).
 */

(function () {
  'use strict';

  const STORAGE_KEY = 'NexFlow.stickyNotes.v1';
  const MAX_NOTES_LIMIT = 100;
  const MAX_FLOATING_LIMIT = 3;

  const PAGE_NAMES = {
    dashboard: 'Dashboard',
    leads: 'Leads',
    pipeline: 'Pipeline',
    contacts: 'Contacts',
    companies: 'Companies',
    tasks: 'Tasks',
    calendar: 'Calendar',
    inbox: 'Inbox',
    reports: 'Reports',
    team: 'Team',
    settings: 'Settings',
    help: 'Help'
  };

  let notes = [];
  let activeFilter = 'all';
  let activeSort = 'updated';
  let searchQuery = '';
  let isOpen = false;
  let editingNoteId = null;
  let deletingNoteId = null;
  let activeDropdownNoteId = null;

  let dragOffset = { x: 0, y: 0 };
  let activeDragNoteId = null;
  let activeDragCard = null;

  // Detect current page key cleanly
  function getPageKey() {
    if (window.NexFlowCurrentPageKey) {
      return String(window.NexFlowCurrentPageKey).toLowerCase().trim();
    }
    const path = window.location.pathname.toLowerCase();
    const filename = path.substring(path.lastIndexOf('/') + 1).replace('.php', '') || 'dashboard';
    return PAGE_NAMES[filename] ? filename : 'dashboard';
  }

  function getPageName(key) {
    return PAGE_NAMES[key] || (key ? key.charAt(0).toUpperCase() + key.slice(1) : 'Page');
  }

  // Generate safe unique ID
  function generateId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
      return 'sn_' + crypto.randomUUID().replace(/-/g, '').substring(0, 12);
    }
    return 'sn_' + Math.random().toString(36).substring(2, 10) + Date.now().toString(36);
  }

  // Escape HTML helper
  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // Date formatting helpers
  function formatDate(isoStr) {
    if (!isoStr) return '';
    try {
      const date = new Date(isoStr);
      if (isNaN(date.getTime())) return '';
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMs / 3600000);

      if (diffMins < 1) return 'Just now';
      if (diffMins < 60) return `${diffMins}m ago`;
      if (diffHours < 24) return `${diffHours}h ago`;
      return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    } catch (e) {
      return '';
    }
  }

  function formatReminder(dateTimeStr) {
    if (!dateTimeStr) return '';
    try {
      const d = new Date(dateTimeStr);
      if (isNaN(d.getTime())) return '';
      return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      return dateTimeStr;
    }
  }

  // Load notes safely from LocalStorage
  function loadNotes() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        notes = [];
        return;
      }
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) {
        notes = parsed.filter(n => n && typeof n === 'object' && n.id && typeof n.content === 'string');
      } else {
        notes = [];
      }
    } catch (e) {
      console.warn('Failed to parse Sticky Notes from localStorage:', e);
      notes = [];
    }
  }

  // Save notes to LocalStorage
  function saveNotes() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(notes));
    } catch (e) {
      console.warn('Failed to save Sticky Notes to localStorage:', e);
    }
    updateBadgeCount();
    renderFloatingNotes();
  }

  // Show Toast Notification
  function showToast(message) {
    if (window.showContactsToast && typeof window.showContactsToast === 'function') {
      window.showContactsToast(message);
      return;
    }
    const existing = document.getElementById('stickyNotesToast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'stickyNotesToast';
    toast.style.cssText = `
      position: fixed;
      bottom: 24px;
      left: 24px;
      background-color: #1E293B;
      color: #FFFFFF;
      padding: 10px 16px;
      border-radius: 8px;
      font-size: 12.5px;
      font-weight: 500;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
      z-index: 2000;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: opacity 0.25s ease;
    `;
    toast.innerHTML = `<span>${escapeHtml(message)}</span>`;
    document.body.appendChild(toast);

    setTimeout(() => {
      if (toast && toast.parentNode) {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 250);
      }
    }, 3000);
  }

  // Update Topbar & Panel Badges
  function updateBadgeCount() {
    const activeNotes = notes.filter(n => !n.archived);
    const badgeEl = document.getElementById('stickyNotesBadge');
    if (badgeEl) {
      if (activeNotes.length > 0) {
        badgeEl.textContent = activeNotes.length > 99 ? '99+' : activeNotes.length;
        badgeEl.style.display = 'inline-flex';
      } else {
        badgeEl.style.display = 'none';
      }
    }

    const panelCountEl = document.getElementById('stickyNotesPanelCount');
    if (panelCountEl) {
      const count = getFilteredNotes().length;
      panelCountEl.textContent = `${count} note${count === 1 ? '' : 's'}`;
    }
  }

  // Get filtered and sorted notes array
  function getFilteredNotes() {
    const currentPageKey = getPageKey();

    return notes.filter(n => {
      // Filter tab
      if (activeFilter === 'pinned') {
        if (!n.pinned || n.archived) return false;
      } else if (activeFilter === 'current-page') {
        if (n.archived) return false;
        if (n.scope !== 'page' || n.pageKey !== currentPageKey) return false;
      } else if (activeFilter === 'archived') {
        if (!n.archived) return false;
      } else {
        // 'all'
        if (n.archived) return false;
      }

      // Search query
      if (searchQuery) {
        const q = searchQuery.toLowerCase();
        const matchTitle = n.title && n.title.toLowerCase().includes(q);
        const matchContent = n.content && n.content.toLowerCase().includes(q);
        if (!matchTitle && !matchContent) return false;
      }

      return true;
    }).sort((a, b) => {
      // Pinned notes first unless viewing archived
      if (activeFilter !== 'archived') {
        if (a.pinned && !b.pinned) return -1;
        if (!a.pinned && b.pinned) return 1;
      }

      if (activeSort === 'created') {
        return new Date(b.createdAt || 0) - new Date(a.createdAt || 0);
      } else if (activeSort === 'title') {
        const tA = (a.title || a.content || '').toLowerCase();
        const tB = (b.title || b.content || '').toLowerCase();
        return tA.localeCompare(tB);
      } else {
        // 'updated'
        return new Date(b.updatedAt || b.createdAt || 0) - new Date(a.updatedAt || a.createdAt || 0);
      }
    });
  }

  // Toggle Panel Open/Close (Single Backdrop)
  function togglePanel(show) {
    isOpen = typeof show === 'boolean' ? show : !isOpen;

    const panel = document.getElementById('stickyNotesPanel');
    const backdrop = document.getElementById('stickyNotesBackdrop');
    const triggerBtn = document.getElementById('topbarNotesBtn');

    if (isOpen) {
      if (panel) {
        panel.classList.add('show');
        panel.setAttribute('aria-hidden', 'false');
      }
      if (backdrop) {
        backdrop.classList.add('show');
        backdrop.setAttribute('aria-hidden', 'false');
      }
      if (triggerBtn) triggerBtn.setAttribute('aria-expanded', 'true');

      renderPanel();
    } else {
      if (panel) {
        panel.classList.remove('show');
        panel.setAttribute('aria-hidden', 'true');
      }
      if (backdrop) {
        backdrop.classList.remove('show');
        backdrop.setAttribute('aria-hidden', 'true');
      }
      if (triggerBtn) triggerBtn.setAttribute('aria-expanded', 'false');
      closeAllCardMenus();
    }
  }

  // Render Panel List
  function renderPanel() {
    updateBadgeCount();
    const listEl = document.getElementById('stickyNotesList');
    if (!listEl) return;

    const filtered = getFilteredNotes();

    if (filtered.length === 0) {
      listEl.innerHTML = `
        <div class="sticky-notes-empty-state">
          <div class="sticky-notes-empty-icon">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M15.5 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V8.5L15.5 3z"/>
              <line x1="9" y1="13" x2="15" y2="13"/>
            </svg>
          </div>
          <h3 class="sticky-notes-empty-title">No sticky notes yet</h3>
          <p class="sticky-notes-empty-desc">Create a note to keep important information close while you work.</p>
        </div>
      `;
      return;
    }

    listEl.innerHTML = filtered.map(n => renderNoteCardHtml(n)).join('');

    // Attach menu triggers & action listeners
    filtered.forEach(n => {
      const trigger = document.getElementById(`snTrigger_${n.id}`);
      if (trigger) {
        trigger.addEventListener('click', (e) => {
          e.stopPropagation();
          toggleCardMenu(n.id);
        });
      }
    });
  }

  // Render individual note card HTML
  function renderNoteCardHtml(n) {
    const colorClass = `color-${n.color || 'yellow'}`;
    const isArchivedClass = n.archived ? 'is-archived' : '';
    const pageBadge = n.scope === 'page' ? `<span class="sticky-notes-badge sticky-notes-badge-page">${escapeHtml(getPageName(n.pageKey))}</span>` : `<span class="sticky-notes-badge sticky-notes-badge-global">Global</span>`;
    const pinBadge = n.pinned ? `<span class="sticky-notes-badge sticky-notes-badge-pinned">📌 Pinned</span>` : '';
    const floatBadge = n.floating ? `<span class="sticky-notes-badge sticky-notes-badge-floating">📌 Floating</span>` : '';
    const archivedBadge = n.archived ? `<span class="sticky-notes-badge sticky-notes-badge-archived">Archived</span>` : '';
    const reminderHtml = n.reminderAt ? `<span class="sticky-notes-reminder-tag">⏰ ${escapeHtml(formatReminder(n.reminderAt))}</span>` : '';

    return `
      <div class="sticky-notes-card ${colorClass} ${isArchivedClass}" id="snCard_${n.id}">
        <div class="sticky-notes-card-header">
          <div class="sticky-notes-card-title-area">
            ${n.title ? `<h4 class="sticky-notes-card-title">${escapeHtml(n.title)}</h4>` : ''}
            <div class="sticky-notes-meta-badges">
              ${pinBadge}
              ${pageBadge}
              ${floatBadge}
              ${archivedBadge}
            </div>
          </div>
          <div class="sticky-notes-card-actions">
            <button type="button" class="sticky-notes-menu-trigger" id="snTrigger_${n.id}" aria-label="Note actions">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>
              </svg>
            </button>
            <div class="sticky-notes-menu-dropdown" id="snMenu_${n.id}">
              <button type="button" class="sticky-notes-menu-item" onclick="window.NexFlowStickyNotes.openEdit('${n.id}')">✏️ Edit</button>
              <button type="button" class="sticky-notes-menu-item" onclick="window.NexFlowStickyNotes.togglePin('${n.id}')">${n.pinned ? '📌 Unpin' : '📌 Pin to top'}</button>
              <button type="button" class="sticky-notes-menu-item" onclick="window.NexFlowStickyNotes.cycleColor('${n.id}')">🎨 Change Color</button>
              <button type="button" class="sticky-notes-menu-item" onclick="window.NexFlowStickyNotes.toggleScope('${n.id}')">${n.scope === 'global' ? '📄 Move to Current Page' : '🌐 Move to Global'}</button>
              <button type="button" class="sticky-notes-menu-item" onclick="window.NexFlowStickyNotes.toggleFloating('${n.id}')">${n.floating ? '❌ Stop Floating' : '📌 Keep Floating'}</button>
              <button type="button" class="sticky-notes-menu-item" onclick="window.NexFlowStickyNotes.duplicateNote('${n.id}')">📋 Duplicate</button>
              <button type="button" class="sticky-notes-menu-item" onclick="window.NexFlowStickyNotes.toggleArchive('${n.id}')">${n.archived ? '📂 Restore' : '📦 Archive'}</button>
              <div class="sticky-notes-menu-divider"></div>
              <button type="button" class="sticky-notes-menu-item danger" onclick="window.NexFlowStickyNotes.promptDelete('${n.id}')">🗑️ Delete</button>
            </div>
          </div>
        </div>

        <div class="sticky-notes-card-body">${escapeHtml(n.content)}</div>

        <div class="sticky-notes-card-footer">
          <div>${reminderHtml}</div>
          <div>${escapeHtml(formatDate(n.updatedAt || n.createdAt))}</div>
        </div>
      </div>
    `;
  }

  // Toggle card dropdown menu
  function toggleCardMenu(noteId) {
    if (activeDropdownNoteId && activeDropdownNoteId !== noteId) {
      closeAllCardMenus();
    }
    const dropdown = document.getElementById(`snMenu_${noteId}`);
    if (dropdown) {
      const isShown = dropdown.classList.contains('show');
      closeAllCardMenus();
      if (!isShown) {
        dropdown.classList.add('show');
        activeDropdownNoteId = noteId;
      }
    }
  }

  function closeAllCardMenus() {
    document.querySelectorAll('.sticky-notes-menu-dropdown.show').forEach(el => el.classList.remove('show'));
    activeDropdownNoteId = null;
  }

  // Open Composer Modal for Create or Edit
  function openComposer(noteId) {
    closeAllCardMenus();
    editingNoteId = noteId || null;

    const modal = document.getElementById('stickyNotesComposerModal');
    const titleEl = document.getElementById('stickyNotesComposerTitle');
    const titleInput = document.getElementById('stickyNoteTitle');
    const contentInput = document.getElementById('stickyNoteContent');
    const idInput = document.getElementById('stickyNoteId');
    const pinCb = document.getElementById('stickyNotePin');
    const reminderInput = document.getElementById('stickyNoteReminder');
    const saveBtn = document.getElementById('stickyNotesSaveBtn');
    const pageDescEl = document.getElementById('stickyNotesCurrentPageDesc');

    if (pageDescEl) {
      pageDescEl.textContent = `Linked to ${getPageName(getPageKey())}`;
    }

    if (noteId) {
      const note = notes.find(n => n.id === noteId);
      if (!note) return;
      if (titleEl) titleEl.textContent = 'Edit Sticky Note';
      if (idInput) idInput.value = note.id;
      if (titleInput) titleInput.value = note.title || '';
      if (contentInput) contentInput.value = note.content || '';
      if (pinCb) pinCb.checked = !!note.pinned;
      if (reminderInput) reminderInput.value = note.reminderAt || '';

      // Set color radio
      const colorRadio = document.querySelector(`input[name="noteColor"][value="${note.color || 'yellow'}"]`);
      if (colorRadio) {
        colorRadio.checked = true;
        updateColorSwatchActive(colorRadio.value);
      }

      // Set scope radio
      const scopeRadio = document.querySelector(`input[name="noteScope"][value="${note.scope || 'global'}"]`);
      if (scopeRadio) scopeRadio.checked = true;
    } else {
      // Check limit for new note
      if (notes.filter(n => !n.archived).length >= MAX_NOTES_LIMIT) {
        showAlertModal({ title: "Note Limit Reached", message: `You have reached the maximum limit of ${MAX_NOTES_LIMIT} active notes. Please archive or delete existing notes first.`, type: "warning" });
        return;
      }

      if (titleEl) titleEl.textContent = 'New Sticky Note';
      if (idInput) idInput.value = '';
      if (titleInput) titleInput.value = '';
      if (contentInput) contentInput.value = '';
      if (pinCb) pinCb.checked = false;
      if (reminderInput) reminderInput.value = '';

      const yellowRadio = document.querySelector('input[name="noteColor"][value="yellow"]');
      if (yellowRadio) {
        yellowRadio.checked = true;
        updateColorSwatchActive('yellow');
      }
      const globalRadio = document.querySelector('input[name="noteScope"][value="global"]');
      if (globalRadio) globalRadio.checked = true;
    }

    updateCharCounters();

    if (modal) {
      modal.style.display = 'flex';
      setTimeout(() => {
        if (contentInput) contentInput.focus();
      }, 50);
    }
  }

  function closeComposer() {
    const modal = document.getElementById('stickyNotesComposerModal');
    if (modal) modal.style.display = 'none';
    editingNoteId = null;
  }

  function updateColorSwatchActive(selectedColor) {
    document.querySelectorAll('.sticky-notes-color-swatch').forEach(sw => {
      const radio = sw.querySelector('input[type="radio"]');
      if (radio && radio.value === selectedColor) {
        sw.classList.add('active');
      } else {
        sw.classList.remove('active');
      }
    });
  }

  function updateCharCounters() {
    const titleInput = document.getElementById('stickyNoteTitle');
    const contentInput = document.getElementById('stickyNoteContent');
    const titleCounter = document.getElementById('stickyNotesTitleCount');
    const contentCounter = document.getElementById('stickyNotesContentCount');
    const saveBtn = document.getElementById('stickyNotesSaveBtn');

    const titleLen = titleInput ? titleInput.value.length : 0;
    const contentVal = contentInput ? contentInput.value : '';
    const contentLen = contentVal.length;

    if (titleCounter) titleCounter.textContent = `${titleLen}/80`;
    if (contentCounter) contentCounter.textContent = `${contentLen}/2000`;

    if (saveBtn) {
      saveBtn.disabled = contentVal.trim().length === 0;
    }
  }

  // Save Note Form Submit
  function saveNoteFormSubmit() {
    const contentInput = document.getElementById('stickyNoteContent');
    const content = contentInput ? contentInput.value.trim() : '';

    if (!content) return;

    const titleInput = document.getElementById('stickyNoteTitle');
    const title = titleInput ? titleInput.value.trim() : '';

    const colorRadio = document.querySelector('input[name="noteColor"]:checked');
    const color = colorRadio ? colorRadio.value : 'yellow';

    const scopeRadio = document.querySelector('input[name="noteScope"]:checked');
    const scope = scopeRadio ? scopeRadio.value : 'global';

    const pinCb = document.getElementById('stickyNotePin');
    const pinned = pinCb ? pinCb.checked : false;

    const reminderInput = document.getElementById('stickyNoteReminder');
    const reminderAt = reminderInput && reminderInput.value ? reminderInput.value : null;

    const now = new Date().toISOString();
    const currentPageKey = getPageKey();

    if (editingNoteId) {
      const idx = notes.findIndex(n => n.id === editingNoteId);
      if (idx >= 0) {
        notes[idx].title = title;
        notes[idx].content = content;
        notes[idx].color = color;
        notes[idx].scope = scope;
        notes[idx].pageKey = scope === 'page' ? (notes[idx].pageKey || currentPageKey) : null;
        notes[idx].pinned = pinned;
        notes[idx].reminderAt = reminderAt;
        notes[idx].updatedAt = now;
      }
      showToast('Sticky note updated');
    } else {
      const newNote = {
        id: generateId(),
        title: title,
        content: content,
        color: color,
        scope: scope,
        pageKey: scope === 'page' ? currentPageKey : null,
        pinned: pinned,
        floating: false,
        archived: false,
        reminderAt: reminderAt,
        position: null,
        createdAt: now,
        updatedAt: now
      };
      notes.unshift(newNote);
      showToast('New sticky note created');
    }

    saveNotes();
    closeComposer();
    if (isOpen) renderPanel();
  }

  // Action methods
  function togglePin(id) {
    closeAllCardMenus();
    const note = notes.find(n => n.id === id);
    if (note) {
      note.pinned = !note.pinned;
      note.updatedAt = new Date().toISOString();
      saveNotes();
      if (isOpen) renderPanel();
      showToast(note.pinned ? 'Note pinned to top' : 'Note unpinned');
    }
  }

  function cycleColor(id) {
    closeAllCardMenus();
    const colors = ['yellow', 'blue', 'green', 'pink', 'purple'];
    const note = notes.find(n => n.id === id);
    if (note) {
      const currentIdx = colors.indexOf(note.color || 'yellow');
      note.color = colors[(currentIdx + 1) % colors.length];
      note.updatedAt = new Date().toISOString();
      saveNotes();
      if (isOpen) renderPanel();
      showToast(`Color changed to ${note.color}`);
    }
  }

  function toggleScope(id) {
    closeAllCardMenus();
    const note = notes.find(n => n.id === id);
    if (note) {
      if (note.scope === 'global') {
        note.scope = 'page';
        note.pageKey = getPageKey();
        showToast(`Moved to ${getPageName(note.pageKey)} page`);
      } else {
        note.scope = 'global';
        note.pageKey = null;
        showToast('Moved to Global notes');
      }
      note.updatedAt = new Date().toISOString();
      saveNotes();
      if (isOpen) renderPanel();
    }
  }

  function toggleArchive(id) {
    closeAllCardMenus();
    const note = notes.find(n => n.id === id);
    if (note) {
      note.archived = !note.archived;
      if (note.archived) note.floating = false; // Stop floating if archived
      note.updatedAt = new Date().toISOString();
      saveNotes();
      if (isOpen) renderPanel();
      showToast(note.archived ? 'Note archived' : 'Note restored');
    }
  }

  function duplicateNote(id) {
    closeAllCardMenus();
    const note = notes.find(n => n.id === id);
    if (!note) return;

    if (notes.filter(n => !n.archived).length >= MAX_NOTES_LIMIT) {
      showAlertModal({ title: "Note Limit Reached", message: `Maximum limit of ${MAX_NOTES_LIMIT} active notes reached.`, type: "warning" });
      return;
    }

    const now = new Date().toISOString();
    const copy = JSON.parse(JSON.stringify(note));
    copy.id = generateId();
    copy.title = copy.title ? `${copy.title} (Copy)` : 'Copy of note';
    copy.floating = false;
    copy.createdAt = now;
    copy.updatedAt = now;

    notes.unshift(copy);
    saveNotes();
    if (isOpen) renderPanel();
    showToast('Note duplicated');
  }

  function promptDelete(id) {
    closeAllCardMenus();
    deletingNoteId = id;
    const modal = document.getElementById('stickyNotesConfirmModal');
    if (modal) modal.style.display = 'flex';
  }

  function confirmDelete() {
    if (deletingNoteId) {
      notes = notes.filter(n => n.id !== deletingNoteId);
      saveNotes();
      if (isOpen) renderPanel();
      showToast('Note deleted');
    }
    closeDeleteConfirm();
  }

  function closeDeleteConfirm() {
    const modal = document.getElementById('stickyNotesConfirmModal');
    if (modal) modal.style.display = 'none';
    deletingNoteId = null;
  }

  // Floating Notes Logic
  function toggleFloating(id) {
    closeAllCardMenus();
    const note = notes.find(n => n.id === id);
    if (!note) return;

    if (!note.floating) {
      const currentFloatingCount = notes.filter(n => n.floating && !n.archived).length;
      if (currentFloatingCount >= MAX_FLOATING_LIMIT) {
        showToast(`Maximum ${MAX_FLOATING_LIMIT} floating notes allowed at a time`);
        return;
      }
      note.floating = true;
      showToast('Note kept floating on page');
    } else {
      note.floating = false;
      showToast('Note floating view closed');
    }
    saveNotes();
    if (isOpen) renderPanel();
  }

  function renderFloatingNotes() {
    const container = document.getElementById('stickyNotesFloatingContainer');
    if (!container) return;

    const floatingNotes = notes.filter(n => n.floating && !n.archived);

    if (floatingNotes.length === 0) {
      container.innerHTML = '';
      return;
    }

    const isMobile = window.innerWidth <= 640;

    container.innerHTML = floatingNotes.map((n, index) => {
      const colorClass = `color-${n.color || 'yellow'}`;
      const hasPos = n.position && typeof n.position.x === 'number' && typeof n.position.y === 'number';

      let styleStr = '';
      if (!isMobile && hasPos) {
        styleStr = `left: ${n.position.x}px; top: ${n.position.y}px;`;
      } else {
        // Smart default position (bottom-right offset away from Softphone button at bottom:24px, right:24px)
        const bottomOffset = 24 + (index * 60);
        styleStr = isMobile ? `bottom: ${bottomOffset}px; right: 16px;` : `bottom: 24px; right: ${330 + (index * 20)}px;`;
      }

      if (n.minimized) {
        return `
          <div class="sticky-notes-floating-chip ${colorClass}" id="snFloat_${n.id}" style="${styleStr}" onclick="window.NexFlowStickyNotes.restoreFloating('${n.id}')">
            <span>📌 ${escapeHtml(n.title || n.content.substring(0, 18))}</span>
            <button type="button" class="sticky-notes-float-btn" onclick="event.stopPropagation(); window.NexFlowStickyNotes.toggleFloating('${n.id}')">✕</button>
          </div>
        `;
      }

      return `
        <div class="sticky-notes-floating-card ${colorClass}" id="snFloat_${n.id}" style="${styleStr}">
          <div class="sticky-notes-floating-header" id="snFloatHeader_${n.id}">
            <span class="sticky-notes-floating-drag-icon">⋮⋮</span>
            <span class="sticky-notes-floating-title">${escapeHtml(n.title || 'Sticky Note')}</span>
            <div class="sticky-notes-floating-actions">
              <button type="button" class="sticky-notes-float-btn" title="Minimize" onclick="window.NexFlowStickyNotes.minimizeFloating('${n.id}')">_</button>
              <button type="button" class="sticky-notes-float-btn" title="Edit" onclick="window.NexFlowStickyNotes.openEdit('${n.id}')">✏️</button>
              <button type="button" class="sticky-notes-float-btn" title="Close" onclick="window.NexFlowStickyNotes.toggleFloating('${n.id}')">✕</button>
            </div>
          </div>
          <div class="sticky-notes-floating-body">${escapeHtml(n.content)}</div>
        </div>
      `;
    }).join('');

    // Attach Drag Handlers for non-mobile floating cards
    if (!isMobile) {
      floatingNotes.forEach(n => {
        if (!n.minimized) {
          const header = document.getElementById(`snFloatHeader_${n.id}`);
          const card = document.getElementById(`snFloat_${n.id}`);
          if (header && card) {
            setupDraggable(header, card, n.id);
          }
        }
      });
    }
  }

  function minimizeFloating(id) {
    const note = notes.find(n => n.id === id);
    if (note) {
      note.minimized = true;
      saveNotes();
    }
  }

  function restoreFloating(id) {
    const note = notes.find(n => n.id === id);
    if (note) {
      note.minimized = false;
      saveNotes();
    }
  }

  // Setup Native Drag and Drop for Floating Cards
  function setupDraggable(headerEl, cardEl, noteId) {
    headerEl.addEventListener('mousedown', onMouseDown);
    headerEl.addEventListener('touchstart', onTouchStart, { passive: false });

    function onMouseDown(e) {
      if (e.target.closest('.sticky-notes-float-btn')) return;
      e.preventDefault();
      activeDragNoteId = noteId;
      activeDragCard = cardEl;

      const rect = cardEl.getBoundingClientRect();
      dragOffset.x = e.clientX - rect.left;
      dragOffset.y = e.clientY - rect.top;

      document.addEventListener('mousemove', onMouseMove);
      document.addEventListener('mouseup', onMouseUp);
    }

    function onMouseMove(e) {
      if (!activeDragCard) return;
      let newX = e.clientX - dragOffset.x;
      let newY = e.clientY - dragOffset.y;

      const maxX = window.innerWidth - activeDragCard.offsetWidth;
      const maxY = window.innerHeight - activeDragCard.offsetHeight;

      newX = Math.max(0, Math.min(newX, maxX));
      newY = Math.max(0, Math.min(newY, maxY));

      activeDragCard.style.left = `${newX}px`;
      activeDragCard.style.top = `${newY}px`;
      activeDragCard.style.bottom = 'auto';
      activeDragCard.style.right = 'auto';
    }

    function onMouseUp(e) {
      if (activeDragCard && activeDragNoteId) {
        const rect = activeDragCard.getBoundingClientRect();
        const note = notes.find(n => n.id === activeDragNoteId);
        if (note) {
          note.position = { x: Math.round(rect.left), y: Math.round(rect.top) };
          try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(notes));
          } catch (err) {}
        }
      }
      activeDragNoteId = null;
      activeDragCard = null;
      document.removeEventListener('mousemove', onMouseMove);
      document.removeEventListener('mouseup', onMouseUp);
    }

    function onTouchStart(e) {
      if (e.target.closest('.sticky-notes-float-btn')) return;
      const touch = e.touches[0];
      activeDragNoteId = noteId;
      activeDragCard = cardEl;

      const rect = cardEl.getBoundingClientRect();
      dragOffset.x = touch.clientX - rect.left;
      dragOffset.y = touch.clientY - rect.top;

      document.addEventListener('touchmove', onTouchMove, { passive: false });
      document.addEventListener('touchend', onTouchEnd);
    }

    function onTouchMove(e) {
      if (!activeDragCard) return;
      e.preventDefault();
      const touch = e.touches[0];
      let newX = touch.clientX - dragOffset.x;
      let newY = touch.clientY - dragOffset.y;

      const maxX = window.innerWidth - activeDragCard.offsetWidth;
      const maxY = window.innerHeight - activeDragCard.offsetHeight;

      newX = Math.max(0, Math.min(newX, maxX));
      newY = Math.max(0, Math.min(newY, maxY));

      activeDragCard.style.left = `${newX}px`;
      activeDragCard.style.top = `${newY}px`;
      activeDragCard.style.bottom = 'auto';
      activeDragCard.style.right = 'auto';
    }

    function onTouchEnd() {
      if (activeDragCard && activeDragNoteId) {
        const rect = activeDragCard.getBoundingClientRect();
        const note = notes.find(n => n.id === activeDragNoteId);
        if (note) {
          note.position = { x: Math.round(rect.left), y: Math.round(rect.top) };
          try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(notes));
          } catch (err) {}
        }
      }
      activeDragNoteId = null;
      activeDragCard = null;
      document.removeEventListener('touchmove', onTouchMove);
      document.removeEventListener('touchend', onTouchEnd);
    }
  }

  // Setup Event Listeners
  function setupEventListeners() {
    // Header trigger button
    const triggerBtn = document.getElementById('topbarNotesBtn');
    if (triggerBtn) {
      triggerBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        togglePanel();
      });
    }

    // Close panel button & backdrop click
    const closeBtn = document.getElementById('stickyNotesCloseBtn');
    if (closeBtn) closeBtn.addEventListener('click', () => togglePanel(false));

    const backdrop = document.getElementById('stickyNotesBackdrop');
    if (backdrop) backdrop.addEventListener('click', () => togglePanel(false));

    // New Note button
    const newBtn = document.getElementById('stickyNotesNewBtn');
    if (newBtn) newBtn.addEventListener('click', () => openComposer(null));

    // Search input
    const searchInput = document.getElementById('stickyNotesSearchInput');
    const searchClear = document.getElementById('stickyNotesSearchClear');

    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        searchQuery = e.target.value.trim();
        if (searchClear) searchClear.style.display = searchQuery ? 'block' : 'none';
        renderPanel();
      });
    }

    if (searchClear) {
      searchClear.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        searchQuery = '';
        searchClear.style.display = 'none';
        renderPanel();
      });
    }

    // Filter Chips
    const filterContainer = document.getElementById('stickyNotesFilterChips');
    if (filterContainer) {
      filterContainer.addEventListener('click', (e) => {
        const chip = e.target.closest('.sticky-notes-chip');
        if (!chip) return;
        const filter = chip.dataset.filter;
        if (!filter) return;

        filterContainer.querySelectorAll('.sticky-notes-chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');

        activeFilter = filter;
        renderPanel();
      });
    }

    // Sort Select
    const sortSelect = document.getElementById('stickyNotesSortSelect');
    if (sortSelect) {
      sortSelect.addEventListener('change', (e) => {
        activeSort = e.target.value;
        renderPanel();
      });
    }

    // Composer inputs & autosave debounce
    const titleInput = document.getElementById('stickyNoteTitle');
    const contentInput = document.getElementById('stickyNoteContent');
    const form = document.getElementById('stickyNotesForm');

    if (titleInput) titleInput.addEventListener('input', updateCharCounters);
    if (contentInput) contentInput.addEventListener('input', updateCharCounters);

    if (form) {
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        saveNoteFormSubmit();
      });
    }

    // Color swatches click handler
    const colorContainer = document.getElementById('stickyNotesColorOptions');
    if (colorContainer) {
      colorContainer.addEventListener('change', (e) => {
        if (e.target.name === 'noteColor') {
          updateColorSwatchActive(e.target.value);
        }
      });
    }

    const composerClose = document.getElementById('stickyNotesComposerClose');
    if (composerClose) composerClose.addEventListener('click', closeComposer);

    const composerCancel = document.getElementById('stickyNotesCancelBtn');
    if (composerCancel) composerCancel.addEventListener('click', closeComposer);

    // Delete confirm modal buttons
    const confirmDeleteBtn = document.getElementById('stickyNotesConfirmDeleteBtn');
    if (confirmDeleteBtn) confirmDeleteBtn.addEventListener('click', confirmDelete);

    const confirmCancelBtn = document.getElementById('stickyNotesConfirmCancel');
    if (confirmCancelBtn) confirmCancelBtn.addEventListener('click', closeDeleteConfirm);

    const confirmCloseBtn = document.getElementById('stickyNotesConfirmClose');
    if (confirmCloseBtn) confirmCloseBtn.addEventListener('click', closeDeleteConfirm);

    // Close card dropdown menus on outside click
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.sticky-notes-card-actions')) {
        closeAllCardMenus();
      }
    });

    // ESC Key priority handler
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const confirmModal = document.getElementById('stickyNotesConfirmModal');
        if (confirmModal && confirmModal.style.display !== 'none') {
          e.stopImmediatePropagation();
          closeDeleteConfirm();
          return;
        }

        const composerModal = document.getElementById('stickyNotesComposerModal');
        if (composerModal && composerModal.style.display !== 'none') {
          e.stopImmediatePropagation();
          closeComposer();
          return;
        }

        if (activeDropdownNoteId) {
          e.stopImmediatePropagation();
          closeAllCardMenus();
          return;
        }

        if (isOpen) {
          e.stopImmediatePropagation();
          togglePanel(false);
          return;
        }
      }
    });
  }

  // Global Public API
  window.NexFlowStickyNotes = {
    init: function () {
      loadNotes();
      setupEventListeners();
      updateBadgeCount();
      renderFloatingNotes();
    },
    open: function () {
      togglePanel(true);
    },
    close: function () {
      togglePanel(false);
    },
    newNote: function () {
      openComposer(null);
    },
    openEdit: function (id) {
      openComposer(id);
    },
    togglePin: togglePin,
    cycleColor: cycleColor,
    toggleScope: toggleScope,
    toggleArchive: toggleArchive,
    duplicateNote: duplicateNote,
    promptDelete: promptDelete,
    toggleFloating: toggleFloating,
    minimizeFloating: minimizeFloating,
    restoreFloating: restoreFloating
  };

  // Auto initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.NexFlowStickyNotes.init());
  } else {
    window.NexFlowStickyNotes.init();
  }
})();

