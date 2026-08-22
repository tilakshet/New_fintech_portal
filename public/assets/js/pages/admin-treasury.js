(function () {
    'use strict';
    const { apiFetch, escapeHtml, formatMoney: money } = window.Verapay;

    const form = document.getElementById('treasury-filters');
    const perPageSelect = document.getElementById('treasury-per-page');
    const tbody = document.getElementById('treasury-tbody');
    const pagination = document.getElementById('treasury-pagination');
    const downloadLink = document.getElementById('treasury-download');
    const colCount = 8;

    let searchDebounce;

    function statusBadgeClass(status) {
        const map = { success: 'badge-success', pending: 'badge-warning', failed: 'badge-danger', cancelled: 'badge-neutral', refunded: 'badge-info' };
        return map[status] || 'badge-neutral';
    }

    function buildParams(page) {
        const params = new URLSearchParams(new FormData(form));
        [...params.keys()].forEach((k) => { if (!params.get(k)) params.delete(k); });
        params.set('page', page);
        params.set('per_page', perPageSelect.value);
        return params;
    }

    function updateDownloadLink() {
        const params = buildParams(1);
        params.delete('page');
        params.delete('per_page');
        downloadLink.href = '/api/admin/treasury/export.php?' + params.toString();
    }

    async function load(page = 1) {
        updateDownloadLink();
        tbody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-8 text-text-secondary">Loading ledger…</td></tr>`;
        const { success, data, message } = await apiFetch('/api/admin/treasury/list.php?' + buildParams(page).toString());

        if (!success) {
            tbody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-10">
                <p class="text-md text-text-primary font-medium mb-1">We couldn't load the ledger.</p>
                <p class="text-sm text-text-secondary mb-4">${escapeHtml(message || 'Please try again.')}</p>
                <button class="btn-secondary" id="treasury-retry">Try again</button>
            </td></tr>`;
            document.getElementById('treasury-retry')?.addEventListener('click', () => load(page));
            pagination.innerHTML = '';
            return;
        }

        if (!data.entries.length) {
            tbody.innerHTML = `<tr><td colspan="${colCount}" class="text-center py-10">
                <p class="text-md text-text-primary font-medium mb-1">No data available in table</p>
                <p class="text-sm text-text-secondary">Try adjusting your filters or search terms.</p>
            </td></tr>`;
            pagination.innerHTML = '';
            return;
        }

        tbody.innerHTML = data.entries.map((row) => `
            <tr>
                <td class="text-text-secondary whitespace-nowrap">${new Date(row.created_at).toLocaleString()}</td>
                <td>
                    <span class="block text-md text-text-primary">${escapeHtml(row.merchant_name)}</span>
                    <span class="block text-sm text-text-secondary">${escapeHtml(row.merchant_email)}</span>
                </td>
                <td class="capitalize">${escapeHtml(row.type)}</td>
                <td class="font-mono text-sm">${escapeHtml(row.reference)}</td>
                <td class="text-success">${row.type === 'deposit' ? money(row.amount) : '—'}</td>
                <td class="text-danger">${row.type === 'withdrawal' ? money(row.amount) : '—'}</td>
                <td class="font-medium">${money(row.running_balance)}</td>
                <td><span class="${statusBadgeClass(row.status)}">${escapeHtml(row.status)}</span></td>
            </tr>`).join('');

        const { page: p, total_pages, total } = data.pagination;
        pagination.innerHTML = `
            <span class="text-sm text-text-secondary">Showing page ${p} of ${total_pages} (${total} total)</span>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-secondary !px-4 !py-2" id="treasury-prev" ${p <= 1 ? 'disabled' : ''}>Previous</button>
                <button type="button" class="btn-secondary !px-4 !py-2" id="treasury-next" ${p >= total_pages ? 'disabled' : ''}>Next</button>
            </div>`;
        document.getElementById('treasury-prev')?.addEventListener('click', () => load(p - 1));
        document.getElementById('treasury-next')?.addEventListener('click', () => load(p + 1));
    }

    form.addEventListener('input', (e) => {
        if (e.target.id === 'tf-search') {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => load(1), 350);
        }
    });
    form.addEventListener('change', (e) => {
        if (e.target.id !== 'tf-search') load(1);
    });
    form.addEventListener('submit', (e) => { e.preventDefault(); load(1); });
    perPageSelect.addEventListener('change', () => load(1));

    load(1);
})();
