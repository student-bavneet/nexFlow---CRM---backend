<?php
// NexFlow CRM — Shared Sticky Notes Partial
?>
<!-- Sticky Notes Backdrop (Single Instance) -->
<div class="sticky-notes-backdrop" id="stickyNotesBackdrop" aria-hidden="true"></div>

<!-- Sticky Notes Slide-Over Right Panel -->
<aside class="sticky-notes-panel" id="stickyNotesPanel" role="dialog" aria-labelledby="stickyNotesPanelTitle" aria-hidden="true">
    <div class="sticky-notes-panel-header">
        <div class="sticky-notes-title-area">
            <div class="sticky-notes-icon-box">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M15.5 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V8.5L15.5 3z"/>
                    <path d="M14 3v6h6"/>
                    <line x1="8" y1="13" x2="16" y2="13"/>
                    <line x1="8" y1="17" x2="13" y2="17"/>
                </svg>
            </div>
            <div>
                <h2 class="sticky-notes-panel-title" id="stickyNotesPanelTitle">Sticky Notes</h2>
                <span class="sticky-notes-count-tag" id="stickyNotesPanelCount">0 notes</span>
            </div>
        </div>
        <div class="sticky-notes-header-actions">
            <button type="button" class="btn btn-primary btn-sm sticky-notes-btn-new" id="stickyNotesNewBtn">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>New Note</span>
            </button>
            <button type="button" class="sticky-notes-close-btn" id="stickyNotesCloseBtn" aria-label="Close Sticky Notes">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Controls Bar: Search Input, Filter Chips, Sort Dropdown -->
    <div class="sticky-notes-controls"> 
        <div class="sticky-notes-search-wrapper">
            <span class="input-icon">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
            </span>
            <input type="text" id="stickyNotesSearchInput" class="input-control input-sm has-icon sticky-notes-search-input" placeholder="Search notes…">
            <button type="button" id="stickyNotesSearchClear" class="sticky-notes-search-clear" aria-label="Clear search" style="display:none;">✕</button>
        </div>

        <div class="sticky-notes-filter-row">
            <div class="sticky-notes-filter-chips" id="stickyNotesFilterChips">
                <button type="button" class="sticky-notes-chip active" data-filter="all">All</button>
                <button type="button" class="sticky-notes-chip" data-filter="pinned">Pinned</button>
                <button type="button" class="sticky-notes-chip" data-filter="current-page">Current Page</button>
                <button type="button" class="sticky-notes-chip" data-filter="archived">Archived</button>
            </div>
            <select id="stickyNotesSortSelect" class="input-control input-sm sticky-notes-sort-select">
                <option value="updated">Recently Updated</option>
                <option value="created">Recently Created</option>
                <option value="title">Title A–Z</option>
            </select>
        </div>
    </div>

    <!-- Notes Card List Container -->
    <div class="sticky-notes-list-wrapper" id="stickyNotesListWrapper">
        <div class="sticky-notes-list" id="stickyNotesList"></div>
    </div>

    <div class="sticky-notes-status-live" aria-live="polite" id="stickyNotesStatusLive"></div>
</aside>

<!-- Note Modal / Composer Overlay -->
<div class="sticky-notes-modal-overlay" id="stickyNotesComposerModal" role="dialog" aria-modal="true" aria-labelledby="stickyNotesComposerTitle" style="display:none;">
    <div class="sticky-notes-modal-content">
        <div class="sticky-notes-modal-header">
            <h3 class="sticky-notes-modal-title" id="stickyNotesComposerTitle">New Sticky Note</h3>
            <button type="button" class="sticky-notes-modal-close" id="stickyNotesComposerClose" aria-label="Close composer">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <form id="stickyNotesForm" onsubmit="return false;">
            <input type="hidden" id="stickyNoteId" value="">
            <div class="sticky-notes-modal-body">
                <!-- Helper Notice -->
                <div class="sticky-notes-notice">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span>Notes are stored locally in this browser. Avoid saving passwords or sensitive information.</span>
                </div>

                <!-- Title Input -->
                <div class="sticky-notes-form-group">
                    <div class="sticky-notes-label-row">
                        <label for="stickyNoteTitle" class="form-label">Title <span class="sticky-notes-optional">(optional)</span></label>
                        <span class="sticky-notes-char-counter" id="stickyNotesTitleCount">0/80</span>
                    </div>
                    <input type="text" id="stickyNoteTitle" class="input-control input-sm" maxlength="80" placeholder="e.g. Follow-up notes for Q3">
                </div>

                <!-- Content Textarea -->
                <div class="sticky-notes-form-group">
                    <div class="sticky-notes-label-row">
                        <label for="stickyNoteContent" class="form-label">Note Content <span class="sticky-notes-required">*</span></label>
                        <span class="sticky-notes-char-counter" id="stickyNotesContentCount">0/2000</span>
                    </div>
                    <textarea id="stickyNoteContent" style="padding:8px 12px;" class="input-control sticky-notes-textarea" rows="5" maxlength="2000" required placeholder="Write your note here…"></textarea>
                </div>

                <!-- Color Theme Selection -->
                <div class="sticky-notes-form-group">
                    <label class="form-label">Color Theme</label>
                    <div class="sticky-notes-color-options" id="stickyNotesColorOptions">
                        <label class="sticky-notes-color-swatch color-yellow active" title="Soft Yellow">
                            <input type="radio" name="noteColor" value="yellow" checked>
                            <span class="sticky-notes-swatch-circle"></span>
                        </label>
                        <label class="sticky-notes-color-swatch color-blue" title="Soft Blue">
                            <input type="radio" name="noteColor" value="blue">
                            <span class="sticky-notes-swatch-circle"></span>
                        </label>
                        <label class="sticky-notes-color-swatch color-green" title="Soft Green">
                            <input type="radio" name="noteColor" value="green">
                            <span class="sticky-notes-swatch-circle"></span>
                        </label>
                        <label class="sticky-notes-color-swatch color-pink" title="Soft Pink">
                            <input type="radio" name="noteColor" value="pink">
                            <span class="sticky-notes-swatch-circle"></span>
                        </label>
                        <label class="sticky-notes-color-swatch color-purple" title="Soft Purple">
                            <input type="radio" name="noteColor" value="purple">
                            <span class="sticky-notes-swatch-circle"></span>
                        </label>
                    </div>
                </div>

                <!-- Scope Selection (Global vs Current Page) -->
                <div class="sticky-notes-form-group">
                    <label class="form-label">Scope &amp; Visibility</label>
                    <div class="sticky-notes-scope-options">
                        <label class="sticky-notes-radio-card">
                            <input type="radio" name="noteScope" value="global" checked>
                            <div class="sticky-notes-radio-content">
                                <span class="sticky-notes-radio-title">Global Note</span>
                                <span class="sticky-notes-radio-desc">Visible across all CRM pages</span>
                            </div>
                        </label>
                        <label class="sticky-notes-radio-card">
                            <input type="radio" name="noteScope" value="page">
                            <div class="sticky-notes-radio-content">
                                <span class="sticky-notes-radio-title">Current Page Note</span>
                                <span class="sticky-notes-radio-desc" id="stickyNotesCurrentPageDesc">Linked to current page</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Pin Checkbox -->
                <div class="sticky-notes-form-row">
                    <label class="sticky-notes-checkbox-label">
                        <input type="checkbox" id="stickyNotePin">
                        <span>Pin note to top</span>
                    </label>
                </div>

                <!-- Reminder Date Input -->
                <div class="sticky-notes-form-group" style="margin-top: 12px;">
                    <label for="stickyNoteReminder" class="form-label">Reminder Date &amp; Time <span class="sticky-notes-optional">(optional)</span></label>
                    <input type="datetime-local" id="stickyNoteReminder" class="input-control input-sm">
                </div>
            </div>

            <div class="sticky-notes-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" id="stickyNotesCancelBtn">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="stickyNotesSaveBtn" disabled>Save Note</button>
            </div>
        </form>
    </div>
</div>

<!-- Confirm Delete Modal -->
<div class="sticky-notes-modal-overlay" id="stickyNotesConfirmModal" role="dialog" aria-modal="true" aria-labelledby="stickyNotesConfirmTitle" style="display:none;">
    <div class="sticky-notes-modal-content sticky-notes-modal-sm">
        <div class="sticky-notes-modal-header">
            <h3 class="sticky-notes-modal-title" id="stickyNotesConfirmTitle" style="color: #DC2626;">Delete Sticky Note</h3>
            <button type="button" class="sticky-notes-modal-close" id="stickyNotesConfirmClose">✕</button>
        </div>
        <div class="sticky-notes-modal-body">
            <p style="font-size: 13.5px; color: var(--text-body); margin: 0;">Are you sure you want to permanently delete this note? This action cannot be undone.</p>
        </div>
        <div class="sticky-notes-modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" id="stickyNotesConfirmCancel">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="stickyNotesConfirmDeleteBtn" style="background-color: #DC2626; color: #FFFFFF; border-color: #DC2626;">Delete</button>
        </div>
    </div>
</div>

<!-- Floating Notes Container -->
<div class="sticky-notes-floating-container" id="stickyNotesFloatingContainer"></div>

