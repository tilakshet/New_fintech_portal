(function () {
    'use strict';
    const { apiFetch, escapeHtml, formatMoney: money } = window.Verapay;

    function statusBadgeClass(status) {
        const map = { success: 'badge-success', pending: 'badge-warning', failed: 'badge-danger', cancelled: 'badge-neutral', refunded: 'badge-info' };
        return map[status] || 'badge-neutral';
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
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-text-secondary">No activity yet. <a href="/deposits" class="text-brand hover:underline">Make a deposit</a> to get started.</td></tr>`;
            return;
        }
        tbody.innerHTML = data.activity.map((t) => `
            <tr>
                <td class="font-mono text-sm">${escapeHtml(t.reference)}</td>
                <td class="capitalize">${escapeHtml(t.type)}</td>
                <td>${escapeHtml(t.method)}</td>
                <td>${money(t.amount, t.currency)}</td>
                <td><span class="${statusBadgeClass(t.status)}">${escapeHtml(t.status)}</span></td>
                <td class="text-text-secondary">${new Date(t.created_at).toLocaleDateString()}</td>
            </tr>`).join('');
    }

    load();
})();
