(function () {
    'use strict';

    window.Verapay = window.Verapay || {};

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const CURRENCY_SYMBOLS = { INR: '₹', USD: '$', EUR: '€', GBP: '£' };

    /** Formats a decimal amount with its currency symbol. Defaults to INR. */
    function formatMoney(amount, currency = 'INR') {
        const symbol = CURRENCY_SYMBOLS[currency] || (currency ? currency + ' ' : '₹');
        return symbol + Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    window.Verapay.formatMoney = formatMoney;

    /** Wrapper around fetch() that always sends the CSRF header and parses JSON. */
    async function apiFetch(url, options = {}) {
        const opts = Object.assign({ headers: {} }, options);
        opts.headers = Object.assign({ 'X-CSRF-Token': csrfToken() }, opts.headers);
        if (opts.body && !(opts.body instanceof FormData) && typeof opts.body !== 'string') {
            opts.body = JSON.stringify(opts.body);
            opts.headers['Content-Type'] = 'application/json';
        }
        const res = await fetch(url, opts);
        let body;
        try {
            body = await res.json();
        } catch (e) {
            body = { success: false, data: null, message: 'Unexpected server response.' };
        }
        if (res.status === 401) {
            window.location.href = '/login';
        }
        if (res.status === 403 && body.message && body.message.toLowerCase().includes('suspend')) {
            window.location.href = '/suspended';
        }
        return { status: res.status, ...body };
    }
    window.Verapay.apiFetch = apiFetch;

    // ---------------- Toasts ----------------
    const toastIcons = {
        success: '<svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>',
        error: '<svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><circle cx="12" cy="16" r="0.9" fill="currentColor" stroke="none"/></svg>',
        warning: '<svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><circle cx="12" cy="16" r="0.9" fill="currentColor" stroke="none"/></svg>',
        info: '<svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><circle cx="12" cy="16" r="0.9" fill="currentColor" stroke="none"/></svg>',
    };
    const toastColor = { success: 'text-success', error: 'text-danger', warning: 'text-warning', info: 'text-info' };

    function showToast(message, type = 'info', persistent = false) {
        const region = document.getElementById('toast-region');
        if (!region) return;
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');
        el.innerHTML = `
            <span class="${toastColor[type]}">${toastIcons[type] || toastIcons.info}</span>
            <span class="flex-1 text-md text-text-primary">${escapeHtml(message)}</span>
            <button type="button" class="btn-icon !w-6 !h-6 shrink-0" aria-label="Dismiss notification">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>`;
        el.querySelector('button').addEventListener('click', () => el.remove());
        region.appendChild(el);
        if (!persistent) {
            setTimeout(() => el.remove(), type === 'error' ? 8000 : 5000);
        }
    }
    window.Verapay.showToast = showToast;

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    window.Verapay.escapeHtml = escapeHtml;

    // ---------------- Mobile sidebar ----------------
    const sidebar = document.getElementById('app-sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('-translate-x-full');
        sidebarBackdrop?.classList.add('hidden');
        sidebarToggle?.setAttribute('aria-expanded', 'false');
    }
    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('-translate-x-full');
        sidebarBackdrop?.classList.remove('hidden');
        sidebarToggle?.setAttribute('aria-expanded', 'true');
    }
    sidebarToggle?.addEventListener('click', () => {
        const isOpen = sidebarToggle.getAttribute('aria-expanded') === 'true';
        isOpen ? closeSidebar() : openSidebar();
    });
    sidebarBackdrop?.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSidebar();
    });

    // ---------------- Generic dropdown (notifications / profile) ----------------
    function setupDropdown(toggleId, panelId, onOpen) {
        const toggle = document.getElementById(toggleId);
        const panel = document.getElementById(panelId);
        if (!toggle || !panel) return;

        function close() {
            panel.classList.add('hidden');
            toggle.setAttribute('aria-expanded', 'false');
        }
        function open() {
            document.querySelectorAll('[aria-haspopup="true"][aria-expanded="true"]').forEach((el) => {
                if (el !== toggle) el.click();
            });
            panel.classList.remove('hidden');
            toggle.setAttribute('aria-expanded', 'true');
            if (typeof onOpen === 'function') onOpen();
        }
        toggle.addEventListener('click', () => {
            toggle.getAttribute('aria-expanded') === 'true' ? close() : open();
        });
        document.addEventListener('click', (e) => {
            if (!panel.contains(e.target) && !toggle.contains(e.target)) close();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                close();
                toggle.focus();
            }
        });
    }

    let notifPanelLoaded = false;
    setupDropdown('notif-toggle', 'notif-panel', async () => {
        if (notifPanelLoaded) return;
        notifPanelLoaded = true;
        const list = document.getElementById('notif-panel-list');
        const { success, data } = await apiFetch('/api/notifications/list.php?limit=5');
        if (!success || !data?.notifications?.length) {
            list.innerHTML = '<div class="px-4 py-6 text-sm text-text-secondary text-center">No notifications yet.</div>';
            return;
        }
        list.innerHTML = data.notifications.map((n) => `
            <a href="/notifications" class="block px-4 py-3 hover:bg-surface-muted ${n.is_read ? '' : 'bg-brand-muted/40'}">
                <span class="block text-md font-semibold text-text-primary">${escapeHtml(n.title)}</span>
                <span class="block text-sm text-text-secondary mt-0.5">${escapeHtml(n.message)}</span>
            </a>`).join('');
    });
    setupDropdown('profile-toggle', 'profile-panel');

    // ---------------- Modals (<dialog>) ----------------
    document.querySelectorAll('[data-modal-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const dialog = document.getElementById(trigger.getAttribute('data-modal-trigger'));
            if (!dialog) return;
            dialog.showModal();
            dialog._returnFocus = trigger;
            dialog.querySelector('input,button,select,textarea')?.focus();
        });
    });
    document.querySelectorAll('dialog').forEach((dialog) => {
        dialog.addEventListener('close', () => {
            dialog._returnFocus?.focus();
        });
        dialog.querySelectorAll('[data-modal-close]').forEach((btn) => {
            btn.addEventListener('click', () => dialog.close());
        });
        dialog.addEventListener('click', (e) => {
            if (e.target === dialog) dialog.close();
        });
    });
    window.Verapay.openModal = (id) => document.getElementById(id)?.showModal();
    window.Verapay.closeModal = (id) => document.getElementById(id)?.close();

    // ---------------- Button loading helper ----------------
    window.Verapay.setButtonLoading = (btn, loading) => {
        if (!btn) return;
        btn.disabled = loading;
        btn.classList.toggle('btn-loading', loading);
    };
})();
