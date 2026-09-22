/**
 * NexFlow CRM — Global Admin Notifications System JavaScript Controller
 * Shared frontend-only notifications manager persisting across all Admin Portal pages.
 * Handles unread badge counters, module notification icons, individual read state,
 * "Mark all read", outside click / Escape key dismissal, cross-page navigation,
 * and target record scrolling/temporary highlighting.
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'NexFlow_admin_notifications_v1';

    // Rich Sample Notifications across all CRM Modules
    const defaultNotifications = [
        {
            id: 'notif-101',
            type: 'lead',
            title: 'New lead: Victoria Chen from Synapse AI',
            sub: 'Lead • Synapse AI',
            time: '1h ago',
            read: false,
            link: 'leads.php?highlight=L-102'
        },
        {
            id: 'notif-102',
            type: 'task',
            title: 'Task due: Demo call with BrightPath Analytics',
            sub: 'Task • BrightPath Analytics',
            time: '2h ago',
            read: false,
            link: 'tasks.php?highlight=TASK-002'
        },
        {
            id: 'notif-103',
            type: 'project',
            title: 'Project deadline approaching: Cloud Infrastructure Upgrade',
            sub: 'Project • Fortis Group',
            time: '3h ago',
            read: false,
            link: 'projects.php?highlight=PRJ-103'
        },
        {
            id: 'notif-104',
            type: 'invoice',
            title: 'Invoice INV-2026-008 ($12,500) is due soon',
            sub: 'Invoice • Acme Corp',
            time: '5h ago',
            read: true,
            link: 'invoices.php?highlight=INV-2026-008'
        },
        {
            id: 'notif-105',
            type: 'contract',
            title: 'Contract Master Services Agreement 2026 expires soon',
            sub: 'Contract • Acme Corp',
            time: 'Yesterday',
            read: true,
            link: 'contracts.php?highlight=CON-101'
        },
        {
            id: 'notif-106',
            type: 'document',
            title: 'New document uploaded: Technical Architecture Deck.pptx',
            sub: 'Document • Synapse AI',
            time: 'Yesterday',
            read: true,
            link: 'documents.php?highlight=DOC-102'
        },
        {
            id: 'notif-107',
            type: 'deal',
            title: 'Deal moved to Negotiation: Enterprise Expansion',
            sub: 'Deal • Acme Corp ($145,000)',
            time: '2 days ago',
            read: true,
            link: 'pipeline.php?highlight=DEAL-101'
        }
    ];

    const TYPE_ICONS = {
        lead: `<svg width="14" height="14" fill="none" stroke="#2563EB" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="17" y1="11" x2="23" y2="11"/></svg>`,
        task: `<svg width="14" height="14" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>`,
        project: `<svg width="14" height="14" fill="none" stroke="#7C3AED" stroke-width="2" viewBox="0 0 24 24"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>`,
        invoice: `<svg width="14" height="14" fill="none" stroke="#D97706" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`,
        contract: `<svg width="14" height="14" fill="none" stroke="#2563EB" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>`,
        document: `<svg width="14" height="14" fill="none" stroke="#4F46E5" stroke-width="2" viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>`,
        deal: `<svg width="14" height="14" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>`,
        company: `<svg width="14" height="14" fill="none" stroke="#0284C7" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M3 7v14M21 7v14M6 11h4M6 15h4M14 11h4M14 15h4M9 3h6v4H9z"/></svg>`,
        contact: `<svg width="14" height="14" fill="none" stroke="#6366F1" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`,
        general: `<svg width="14" height="14" fill="none" stroke="#2563EB" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>`
    };

    function loadNotifications() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                const parsed = JSON.parse(stored);
                if (Array.isArray(parsed) && parsed.length > 0) return parsed;
            }
        } catch (e) {
            console.warn('Unable to read notifications from localStorage:', e);
        }
        return JSON.parse(JSON.stringify(defaultNotifications));
    }

    function saveNotifications(items) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
        } catch (e) {
            console.warn('Unable to save notifications to localStorage:', e);
        }
    }

    let notifications = loadNotifications();

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderNotifications() {
        const container = document.getElementById('notifListContainer');
        const badge = document.getElementById('notifBadge');
        const tag = document.getElementById('notifCountTag');

        const unreadCount = notifications.filter(n => !n.read).length;

        if (badge) {
            badge.style.display = unreadCount > 0 ? 'block' : 'none';
        }
        if (tag) {
            tag.textContent = unreadCount;
        }

        if (!container) return;

        if (notifications.length === 0) {
            container.innerHTML = `
                <div style="padding: 28px 16px; text-align: center; color: var(--text-muted); font-size: 13px;">
                    No notifications
                </div>
            `;
            return;
        }

        container.innerHTML = notifications.map(n => {
            const iconSvg = TYPE_ICONS[n.type] || TYPE_ICONS.general;
            return `
                <div class="notif-item" data-id="${n.id}" onclick="window.NexFlowNotif.clickItem('${n.id}', '${n.link || ''}')" style="padding: 12px 16px; border-bottom: 1px solid var(--border-divider); display: flex; gap: 12px; background-color: ${n.read ? '#FFFFFF' : '#F5F8FF'}; cursor: pointer; transition: background-color 0.15s ease; position: relative;">
                    ${!n.read ? '<span style="position: absolute; left: 6px; top: 50%; transform: translateY(-50%); width: 5px; height: 5px; border-radius: 50%; background-color: #2563EB;"></span>' : ''}
                    <div style="width: 30px; height: 30px; border-radius: 50%; background-color: var(--primary-light, #EFF6FF); display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">
                        ${iconSvg}
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <p style="font-size: 13px; color: var(--text-heading); font-weight: ${n.read ? '400' : '600'}; line-height: 1.3; margin: 0;">${escapeHtml(n.title)}</p>
                        ${n.sub ? `<span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px;">${escapeHtml(n.sub)}</span>` : ''}
                        <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 2px;">${escapeHtml(n.time || 'Just now')}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function handleUrlHighlight() {
        const urlParams = new URLSearchParams(window.location.search);
        const highlightId = urlParams.get('highlight');
        if (!highlightId) return;

        let retries = 0;
        const maxRetries = 25; // Retry for up to 2.5s (25 * 100ms)

        const interval = setInterval(function () {
            retries++;

            let target = document.querySelector(
                `[data-id="${highlightId}"], ` +
                `[data-record-id="${highlightId}"], ` +
                `[data-lead-id="${highlightId}"], ` +
                `[data-task-id="${highlightId}"], ` +
                `[data-project-id="${highlightId}"], ` +
                `[data-invoice-id="${highlightId}"], ` +
                `[data-contract-id="${highlightId}"], ` +
                `[data-doc-id="${highlightId}"], ` +
                `[data-deal-id="${highlightId}"], ` +
                `[data-company-id="${highlightId}"], ` +
                `[data-contact-id="${highlightId}"], ` +
                `[data-ts-id="${highlightId}"], ` +
                `#${highlightId}`
            );

            if (!target) {
                const elements = document.querySelectorAll('tr[data-id], .tasks-item, .project-card, .kanban-card, .document-card, .timesheet-entry-card, tr');
                for (let el of elements) {
                    const elId = el.getAttribute('data-id') || el.getAttribute('id') || '';
                    if (elId && (elId === highlightId || elId.includes(highlightId) || highlightId.includes(elId))) {
                        target = el;
                        break;
                    }
                }
            }

            if (target) {
                clearInterval(interval);

                // Smooth scroll to record
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });

                // Temporary visual highlight
                target.classList.add('notification-target-highlight');

                // Remove highlight smoothly after 3000ms
                setTimeout(function () {
                    target.classList.add('fade-out-highlight');
                    setTimeout(function () {
                        target.classList.remove('notification-target-highlight', 'fade-out-highlight');
                    }, 500);
                }, 3000);
            } else if (retries >= maxRetries) {
                clearInterval(interval);
            }
        }, 100);
    }

    window.NexFlowNotif = {
        init: function () {
            notifications = loadNotifications();
            renderNotifications();
            handleUrlHighlight();
        },

        add: function (data) {
            const newItem = {
                id: 'notif-' + Date.now(),
                type: data.type || 'general',
                title: data.title || 'New CRM activity',
                sub: data.sub || '',
                time: 'Just now',
                read: false,
                link: data.link || ''
            };
            notifications.unshift(newItem);
            saveNotifications(notifications);
            renderNotifications();
        },

        markAllRead: function () {
            notifications.forEach(n => { n.read = true; });
            saveNotifications(notifications);
            renderNotifications();
        },

        clickItem: function (id, link) {
            const item = notifications.find(n => n.id === id);
            if (item) {
                item.read = true;
                saveNotifications(notifications);
                renderNotifications();
            }

            const notifDropdown = document.getElementById('notifDropdown');
            if (notifDropdown) notifDropdown.classList.remove('show');

            if (link && link !== '#') {
                window.location.href = link;
            }
        }
    };

    window.markAllNotificationsRead = function () {
        window.NexFlowNotif.markAllRead();
    };

    // Close notifications popover on outside click or ESC key
    document.addEventListener('click', function (e) {
        const notifDropdown = document.getElementById('notifDropdown');
        const notifBtn = document.getElementById('notifBtn');
        if (notifDropdown && notifBtn && notifDropdown.classList.contains('show')) {
            if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                notifDropdown.classList.remove('show');
            }
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const notifDropdown = document.getElementById('notifDropdown');
            if (notifDropdown && notifDropdown.classList.contains('show')) {
                notifDropdown.classList.remove('show');
            }
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            window.NexFlowNotif.init();
        });
    } else {
        window.NexFlowNotif.init();
    }
})();

