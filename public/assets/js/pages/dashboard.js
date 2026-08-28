(function () {
    'use strict';
    const { apiFetch, showToast, renderTrendChart, escapeHtml, formatMoney: money } = window.Verapay;

    const root = document.getElementById('dashboard-root');
    if (!root) return;
    const isOperator = root.dataset.role === 'admin' || root.dataset.role === 'operator';

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

    function fillReportGrid(prefix, report) {
        ['total', 'success', 'pending', 'failed'].forEach((key) => {
            const entry = report[key];
            const amountEl = document.getElementById(`${prefix}-${key}-amount`);
            const countEl = document.getElementById(`${prefix}-${key}-count`);
            if (amountEl) amountEl.textContent = money(entry.amount);
            if (countEl) countEl.textContent = `${entry.count} transaction${entry.count === 1 ? '' : 's'}`;
        });
    }

    async function load() {
        const { success, data, message } = await apiFetch('/api/dashboard/summary.php');
        if (!success) {
            document.getElementById('primary-balance-card').innerHTML = `<div class="text-center py-4">
                <p class="text-md text-text-primary font-medium mb-1">We couldn't load your dashboard.</p>
                <p class="text-sm text-text-secondary mb-4">${escapeHtml(message || 'Please try again.')}</p>
                <button class="btn-secondary" onclick="location.reload()">Try again</button>
            </div>`;
            return;
        }

        // Primary balance panel
        if (!isOperator && data.wallet) {
            document.getElementById('primary-balance-amount').textContent =
                money((parseFloat(data.wallet.available_balance) + parseFloat(data.wallet.pending_balance)).toFixed(2), data.wallet.currency);
            document.getElementById('primary-balance-sub1-label').textContent = 'Available';
            document.getElementById('primary-balance-sub1-value').textContent = money(data.wallet.available_balance, data.wallet.currency);
            document.getElementById('primary-balance-sub2-label').textContent = 'Pending';
            document.getElementById('primary-balance-sub2-value').textContent = money(data.wallet.pending_balance, data.wallet.currency);
        } else {
            const todayTotal = (parseFloat(data.deposits_report.total.amount) + parseFloat(data.withdrawals_report.total.amount));
            document.getElementById('primary-balance-amount').textContent = money(todayTotal.toFixed(2));
            document.getElementById('primary-balance-sub1-label').textContent = "Today's transactions";
            document.getElementById('primary-balance-sub1-value').textContent = data.today_count;
            document.getElementById('primary-balance-sub2-label').textContent = 'Successful deposits';
            document.getElementById('primary-balance-sub2-value').textContent = data.deposits_report.success.count;
        }

        // Report card grids
        fillReportGrid('dep', data.deposits_report);
        fillReportGrid('wd', data.withdrawals_report);

        // Analytics
        renderTrendChart(
            document.getElementById('deposits-chart'),
            data.deposits_trend,
            'var(--color-brand)',
            (v) => money(v.toFixed(2)),
            'deposits-chart-title'
        );
        renderTrendChart(
            document.getElementById('withdrawals-chart'),
            data.withdrawals_trend,
            'var(--color-info)',
            (v) => money(v.toFixed(2)),
            'withdrawals-chart-title'
        );

        // Recent activity
        const tbody = document.getElementById('recent-tbody');
        if (!data.recent.length) {
            tbody.innerHTML = `<tr><td colspan="${isOperator ? 6 : 5}">
                <div class="empty-state">
                    <span class="empty-state-icon"><svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h4l2 3h4l2-3h4"/><path d="M5.5 5h13L21 12v6a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18v-6Z"/></svg></span>
                    <p class="empty-state-title">No transactions yet</p>
                    ${isOperator ? '' : '<p class="empty-state-body">Add funds to your wallet to see activity here.</p><a href="/deposits" class="btn-primary">Make a deposit</a>'}
                </div>
            </td></tr>`;
            return;
        }
        tbody.innerHTML = data.recent.map((t) => `
            <tr class="border-l-4 ${statusBorderClass(t.status)}">
                ${isOperator ? `<td>${escapeHtml(t.user_name)}</td>` : ''}
                <td>
                    <span class="inline-flex items-center gap-2.5">
                        ${typeIconChip(t.type)}
                        <span class="font-mono text-sm">${escapeHtml(t.reference)}</span>
                    </span>
                </td>
                <td class="capitalize">${escapeHtml(t.type)}</td>
                <td>${money(t.amount, t.currency)}</td>
                <td><span class="${statusBadgeClass(t.status)}">${escapeHtml(t.status)}</span></td>
                <td class="text-text-secondary">${new Date(t.created_at).toLocaleDateString()}</td>
            </tr>`).join('');
    }

    load();
})();
