(function () {
    'use strict';
    const { apiFetch, escapeHtml, formatMoney: money } = window.Verapay;

    function statusBadgeClass(status) {
        const map = { success: 'badge-success', pending: 'badge-warning', failed: 'badge-danger', cancelled: 'badge-neutral', refunded: 'badge-info' };
        return map[status] || 'badge-neutral';
    }

    function statusBorderClass(status) {
        const map = { success: 'border-l-success', pending: 'border-l-warning', failed: 'border-l-danger', cancelled: 'border-l-neutral', refunded: 'border-l-info' };
        return map[status] || 'border-l-neutral';
    }

    function typeIconChip(type) {
        const tone = type === 'deposit' ? 'icon-chip-success' : 'icon-chip-warning';
        const path = type === 'deposit'
            ? '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8.5 11.5 12 15l3.5-3.5"/>'
            : '<circle cx="12" cy="12" r="9"/><path d="M12 16V8M8.5 12.5 12 9l3.5 3.5"/>';
        return `<span class="icon-chip-sm ${tone}"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${path}</svg></span>`;
    }

    async function load() {
        const { success, data, message } = await apiFetch('/api/wallet/summary.php');
        if (!success) {
            document.getElementById('wallet-available').textContent = '—';
            document.getElementById('wallet-pending').textContent = '—';
            document.getElementById('wallet-activity-tbody').innerHTML = `<tr><td colspan="6" class="text-center py-8 text-text-secondary">${escapeHtml(message || "We couldn't load your wallet.")}</td></tr>`;
            return;
        }
        document.getElementById('wallet-available').textContent = money(data.wallet.available_balance, data.wallet.currency);
        document.getElementById('wallet-pending').textContent = money(data.wallet.pending_balance, data.wallet.currency);

        const tbody = document.getElementById('wallet-activity-tbody');
        if (!data.activity.length) {
            tbody.innerHTML = `<tr><td colspan="6">
                <div class="empty-state">
                    <span class="empty-state-icon"><svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h4l2 3h4l2-3h4"/><path d="M5.5 5h13L21 12v6a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18v-6Z"/></svg></span>
                    <p class="empty-state-title">No activity yet</p>
                    <p class="empty-state-body">Add funds to your wallet to see activity here.</p>
                    <a href="/deposits" class="btn-primary">Make a deposit</a>
                </div>
            </td></tr>`;
            return;
        }
        tbody.innerHTML = data.activity.map((t) => `
            <tr class="border-l-4 ${statusBorderClass(t.status)}">
                <td>
                    <span class="inline-flex items-center gap-2.5">
                        ${typeIconChip(t.type)}
                        <span class="font-mono text-sm">${escapeHtml(t.reference)}</span>
                    </span>
                </td>
                <td class="capitalize">${escapeHtml(t.type)}</td>
                <td>${escapeHtml(t.method)}</td>
                <td>${money(t.amount, t.currency)}</td>
                <td><span class="${statusBadgeClass(t.status)}">${escapeHtml(t.status)}</span></td>
                <td class="text-text-secondary">${new Date(t.created_at).toLocaleDateString()}</td>
            </tr>`).join('');
    }

    load();
})();
