(function () {
    'use strict';
    const { apiFetch, showToast, escapeHtml } = window.Verapay;

    const iconSvg = {
        deposit: '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8.5 11.5 12 15l3.5-3.5"/>',
        withdrawal: '<circle cx="12" cy="12" r="9"/><path d="M12 16V8M8.5 12.5 12 9l3.5 3.5"/>',
        support: '<path d="M4 5.5h16v10H9.5L5 19v-3.5H4Z"/><path d="M8 9.5h8M8 12.5h5"/>',
        security: '<path d="M12 3.5 19.5 6.5V11c0 5-3.2 8.2-7.5 9.5C7.7 19.2 4.5 16 4.5 11V6.5L12 3.5Z"/>',
    };
    function iconFor(type) {
        const path = iconSvg[type] || '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><circle cx="12" cy="16" r="0.9" fill="currentColor" stroke="none"/>';
        return `<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
    }

    function timeLabel(iso) {
        return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    async function load() {
        const list = document.getElementById('notifications-list');
        const { success, data, message } = await apiFetch('/api/notifications/list.php?limit=50');
        if (!success) {
            list.innerHTML = `<li class="px-5 py-8 text-center">
                <p class="text-md text-text-primary font-medium mb-1">We couldn't load your notifications.</p>
                <p class="text-sm text-text-secondary">${escapeHtml(message || 'Please try again.')}</p>
            </li>`;
            return;
        }
        if (!data.notifications.length) {
            list.innerHTML = `<li class="px-5 py-10 text-center">
                <p class="text-md text-text-primary font-medium mb-1">No notifications yet</p>
                <p class="text-sm text-text-secondary">Payment, support, and security updates will appear here.</p>
            </li>`;
            return;
        }
        list.innerHTML = data.notifications.map((n) => `
            <li class="flex items-start gap-3 px-5 py-4 transition-colors duration-instant hover:bg-surface-muted ${n.is_read ? '' : 'bg-brand-muted/30'}" data-id="${n.id}">
                <span class="flex items-center justify-center w-9 h-9 rounded-full bg-surface-muted text-text-secondary shrink-0">${iconFor(n.type)}</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-md font-semibold text-text-primary">${escapeHtml(n.title)}</span>
                    <span class="block text-sm text-text-secondary mt-0.5">${escapeHtml(n.message)}</span>
                    <span class="block text-xs text-text-secondary mt-1">${timeLabel(n.created_at)}</span>
                </span>
                ${!n.is_read ? `<button type="button" class="mark-read-btn text-sm text-brand font-medium hover:underline shrink-0" data-id="${n.id}">Mark as read</button>` : ''}
            </li>`).join('');

        list.querySelectorAll('.mark-read-btn').forEach((btn) => btn.addEventListener('click', async () => {
            await apiFetch('/api/notifications/mark-read.php', { method: 'POST', body: { id: btn.dataset.id } });
            load();
        }));
    }

    document.getElementById('mark-all-read').addEventListener('click', async () => {
        const { success } = await apiFetch('/api/notifications/mark-read.php', { method: 'POST', body: { all: true } });
        if (success) load(); else showToast('Unable to update notifications.', 'error');
    });

    load();
})();
