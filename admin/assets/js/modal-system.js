/**
 * NexFlow CRM - Centralized Custom System Modal Engine
 * Replaces native browser alert(), confirm(), prompt() dialogs with
 * accessible, CRM-styled modals matching the NexFlow design language.
 */
(function(window, document) {
    'use strict';

    let activeModalInstance = null;
    let previousActiveElement = null;

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getIconSvg(type) {
        switch (type) {
            case 'danger':
            case 'destructive':
            case 'error':
                return `<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>`;
            case 'warning':
                return `<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`;
            case 'success':
                return `<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`;
            case 'info':
            case 'primary':
            default:
                return `<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`;
        }
    }

    function createModalDom() {
        let overlay = document.getElementById('lfModalSystemOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'lfModalSystemOverlay';
            overlay.className = 'lf-modal-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.innerHTML = `<div class="lf-modal-card" id="lfModalSystemCard"></div>`;
            document.body.appendChild(overlay);

            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    if (activeModalInstance && activeModalInstance.allowBackdropClose) {
                        NexFlowModal.close('backdrop');
                    }
                }
            });
        }
        return overlay;
    }

    function handleKeyDown(e) {
        if (!activeModalInstance) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            NexFlowModal.close('escape');
        } else if (e.key === 'Enter' && activeModalInstance.mode === 'prompt') {
            const input = document.getElementById('lfModalSystemInput');
            if (input && document.activeElement === input) {
                e.preventDefault();
                NexFlowModal.submit();
            }
        }
    }

    const NexFlowModal = {
        show: function(options) {
            options = options || {};
            previousActiveElement = document.activeElement;

            const mode = options.mode || 'alert'; // 'alert' | 'confirm' | 'prompt'
            const type = options.type || (mode === 'confirm' ? 'danger' : 'info');
            const title = options.title || (type === 'danger' ? 'Confirm Action' : 'Notice');
            const message = options.message || '';
            const confirmText = options.confirmText || (mode === 'prompt' ? 'Save' : (type === 'danger' ? 'Delete' : (mode === 'confirm' ? 'Confirm' : 'OK')));
            const cancelText = options.cancelText || 'Cancel';
            const allowBackdropClose = options.allowBackdropClose !== undefined ? options.allowBackdropClose : (type !== 'danger' && type !== 'destructive');

            activeModalInstance = {
                mode: mode,
                type: type,
                onConfirm: options.onConfirm || options.onSuccess || null,
                onCancel: options.onCancel || null,
                allowBackdropClose: allowBackdropClose
            };

            const overlay = createModalDom();
            const card = document.getElementById('lfModalSystemCard');

            let bodyContentHtml = `<p style="margin:0;white-space:pre-wrap;">${escapeHtml(message)}</p>`;

            if (mode === 'prompt') {
                const label = options.label || '';
                const placeholder = options.placeholder || '';
                const defaultValue = options.value !== undefined ? options.value : '';

                bodyContentHtml += `
                    <div class="lf-modal-input-wrapper">
                        ${label ? `<label class="lf-modal-input-label">${escapeHtml(label)}</label>` : ''}
                        <input type="text" class="lf-modal-input" id="lfModalSystemInput" value="${escapeHtml(defaultValue)}" placeholder="${escapeHtml(placeholder)}" autocomplete="off">
                    </div>
                `;
            }

            const btnConfirmClass = (type === 'danger' || type === 'destructive') ? 'btn-danger' : 'btn-primary';

            card.innerHTML = `
                <div class="lf-modal-header">
                    <div class="lf-modal-header-left">
                        <div class="lf-modal-icon-badge ${type}">
                            ${getIconSvg(type)}
                        </div>
                        <h3 class="lf-modal-title">${escapeHtml(title)}</h3>
                    </div>
                    <button type="button" class="lf-modal-close-btn" aria-label="Close modal" onclick="window.NexFlowModal.close('close-button')">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="lf-modal-body">
                    ${bodyContentHtml}
                </div>
                <div class="lf-modal-footer">
                    ${mode !== 'alert' ? `
                        <button type="button" class="btn btn-secondary btn-sm" onclick="window.NexFlowModal.close('cancel')">${escapeHtml(cancelText)}</button>
                    ` : ''}
                    <button type="button" class="btn ${btnConfirmClass} btn-sm" id="lfModalSystemConfirmBtn" onclick="window.NexFlowModal.submit()">${escapeHtml(confirmText)}</button>
                </div>
            `;

            overlay.classList.add('is-active');
            document.addEventListener('keydown', handleKeyDown);

            setTimeout(function() {
                if (mode === 'prompt') {
                    const input = document.getElementById('lfModalSystemInput');
                    if (input) {
                        input.focus();
                        input.select();
                    }
                } else {
                    const confirmBtn = document.getElementById('lfModalSystemConfirmBtn');
                    if (confirmBtn) confirmBtn.focus();
                }
            }, 50);
        },

        confirm: function(titleOrOptions, message, onConfirm, type) {
            if (typeof titleOrOptions === 'object') {
                return NexFlowModal.show(Object.assign({ mode: 'confirm' }, titleOrOptions));
            }
            return NexFlowModal.show({
                mode: 'confirm',
                title: titleOrOptions || 'Confirm Action',
                message: message || '',
                onConfirm: onConfirm,
                type: type || 'danger'
            });
        },

        prompt: function(titleOrOptions, label, defaultValue, onConfirm) {
            if (typeof titleOrOptions === 'object') {
                return NexFlowModal.show(Object.assign({ mode: 'prompt' }, titleOrOptions));
            }
            return NexFlowModal.show({
                mode: 'prompt',
                title: titleOrOptions || 'Enter Information',
                label: label || '',
                value: defaultValue || '',
                onConfirm: onConfirm,
                type: 'primary'
            });
        },

        alert: function(titleOrOptions, message, type) {
            if (typeof titleOrOptions === 'object') {
                return NexFlowModal.show(Object.assign({ mode: 'alert' }, titleOrOptions));
            }
            return NexFlowModal.show({
                mode: 'alert',
                title: titleOrOptions || 'Notice',
                message: message || '',
                type: type || 'info'
            });
        },

        submit: function() {
            if (!activeModalInstance) return;
            const instance = activeModalInstance;
            let resultVal = true;

            if (instance.mode === 'prompt') {
                const input = document.getElementById('lfModalSystemInput');
                resultVal = input ? input.value : '';
            }

            NexFlowModal.close('submit');

            if (typeof instance.onConfirm === 'function') {
                instance.onConfirm(resultVal);
            }
        },

        close: function(reason) {
            const overlay = document.getElementById('lfModalSystemOverlay');
            if (overlay) overlay.classList.remove('is-active');
            document.removeEventListener('keydown', handleKeyDown);

            const instance = activeModalInstance;
            activeModalInstance = null;

            if (reason !== 'submit' && instance && typeof instance.onCancel === 'function') {
                instance.onCancel(reason);
            }

            if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
                try { previousActiveElement.focus(); } catch (e) {}
            }
        }
    };

    // Global Alias Helpers
    window.NexFlowModal = NexFlowModal;
    window.LeadFlowModal = NexFlowModal;
    window.showConfirmModal = NexFlowModal.confirm;
    window.showPromptModal = NexFlowModal.prompt;
    window.showAlertModal = NexFlowModal.alert;

    // Overriding native browser dialogs safely as fallback
    window.nativeAlert = window.alert;
    window.nativeConfirm = window.confirm;
    window.nativePrompt = window.prompt;

    window.alert = function(msg) {
        NexFlowModal.alert('Notice', String(msg || ''));
    };

})(window, document);

