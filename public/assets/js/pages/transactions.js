(function () {
    'use strict';
    const { apiFetch, escapeHtml, formatMoney: money } = window.Verapay;

    const isOperator = document.querySelector('#txn-table thead th')?.textContent.trim() === 'Customer';
    const form = document.getElementById('filters-form');
    const tbody = document.getElementById('txn-tbody');
    const pagination = document.getElementById('txn-pagination');
    const colCount = isOperator ? 7 : 6;

    let currentPage = 1;
    let searchDebounce;

    function statusBadgeClass(status) {
        const map = { success: 'badge-success', pending: 'badge-warning', failed: 'badge-danger', cancelled: 'badge-neutral', refunded: 'badge-info' };
        return map[status] || 'badge-neutral';
    }

    function buildQuery(page) {
        const params = new URLSearchParams(new FormData(form));
        [...params.keys()].forEach((k) => { if (!params.get(k)) params.delete(k); });
        params.set('page', page);
        params.set('per_page', 15);
        return params.toString();
    }

    async function load(page = 1) {
        currentPage = page;
        tbody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-8 text-text-secondary">Loading transactions…</td></tr>`;
        const { success, data, message } = await apiFetch('/api/transactions/list.php?' + buildQuery(page));

        if (!success) {
            tbody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-10">
                <p class="text-md text-text-primary font-medium mb-1">We couldn't load transactions.</p>
                <p class="text-sm text-text-secondary mb-4">${escapeHtml(message || 'Please try again.')}</p>
                <button class="btn-secondary" id="txn-retry">Try again</button>
            </td></tr>`;
            document.getElementById('txn-retry')?.addEventListener('click', () => load(page));
            pagination.innerHTML = '';
            return;
        }

        if (!data.transactions.length) {
            tbody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-10">
                <p class="text-md text-text-primary font-medium mb-1">No transactions found</p>
                <p class="text-sm text-text-secondary">Try adjusting your filters or search terms.</p>
            </td></tr>`;
            pagination.innerHTML = '';
            return;
        }

        tbody.innerHTML = data.transactions.map((t) => `
            <tr>
                ${isOperator ? `<td>
                    <span class="block text-md text-text-primary">${escapeHtml(t.user_name)}</span>
                    <span class="block text-sm text-text-secondary">${escapeHtml(t.user_email)}</span>
                </td>` : ''}
                <td class="font-mono text-sm">${escapeHtml(t.reference)}</td>
                <td class="capitalize">${escapeHtml(t.type)}</td>
                <td>${escapeHtml(t.method)}</td>
                <td>${money(t.amount)}</td>
                <td><span class="${statusBadgeClass(t.status)}">${escapeHtml(t.status)}</span></td>
                <td class="text-text-secondary whitespace-nowrap">${new Date(t.created_at).toLocaleString()}</td>
            </tr>`).join('');

        const { page: p, total_pages, total } = data.pagination;
        pagination.innerHTML = `
            <span class="text-sm text-text-secondary">Showing page ${p} of ${total_pages} (${total} total)</span>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-secondary !px-4 !py-2" id="txn-prev" ${p <= 1 ? 'disabled' : ''}>Previous</button>
                <button type="button" class="btn-secondary !px-4 !py-2" id="txn-next" ${p >= total_pages ? 'disabled' : ''}>Next</button>
            </div>`;
        document.getElementById('txn-prev')?.addEventListener('click', () => load(p - 1));
        document.getElementById('txn-next')?.addEventListener('click', () => load(p + 1));
    }

    form.addEventListener('input', (e) => {
        if (e.target.id === 'f-search') {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => load(1), 350);
        }
    });
    form.addEventListener('change', (e) => {
        if (e.target.id !== 'f-search') load(1);
    });
    form.addEventListener('submit', (e) => { e.preventDefault(); load(1); });

    load(1);
})();
