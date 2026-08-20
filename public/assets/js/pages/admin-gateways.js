(function () {
    'use strict';
    const { apiFetch, showToast, setButtonLoading, escapeHtml, openModal, closeModal } = window.Verapay;

    const providerNames = {
        razorpay: 'Razorpay', payu: 'PayU', cashfree: 'Cashfree',
        stripe: 'Stripe', paypal: 'PayPal', other: 'Other',
    };

    const trashIcon = '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0-.7 12.1a2 2 0 0 1-2 1.9H9.7a2 2 0 0 1-2-1.9L7 7h10Z"/></svg>';

    const tbody = document.getElementById('gateways-tbody');
    let pendingDeleteId = null;

    function timeLabel(iso) {
        return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    async function load() {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-text-secondary">Loading gateways…</td></tr>';
        const { success, data, message } = await apiFetch('/api/admin/gateways/list.php');

        if (!success) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-10">
                <p class="text-md text-text-primary font-medium mb-1">We couldn't load payment gateways.</p>
                <p class="text-sm text-text-secondary mb-4">${escapeHtml(message || 'Please try again.')}</p>
                <button class="btn-secondary" id="gw-retry">Try again</button>
            </td></tr>`;
            document.getElementById('gw-retry')?.addEventListener('click', load);
            return;
        }

        if (!data.gateways.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-10">
                <p class="text-md text-text-primary font-medium mb-1">No payment gateways configured</p>
                <p class="text-sm text-text-secondary">Add one to start routing transactions through it.</p>
            </td></tr>`;
            return;
        }

        tbody.innerHTML = data.gateways.map((g) => `
            <tr>
                <td>
                    <span class="flex items-center gap-2">
                        <span class="text-md font-medium text-text-primary">${escapeHtml(g.display_name)}</span>
                        ${g.is_default ? '<span class="badge-info">Default</span>' : ''}
                    </span>
                    <span class="block text-sm text-text-secondary mt-0.5">Added ${timeLabel(g.created_at)}</span>
                </td>
                <td>${escapeHtml(providerNames[g.provider] || g.provider)}</td>
                <td class="font-mono text-sm">•••• ${escapeHtml(g.api_key_last4)}</td>
                <td><span class="${g.status === 'active' ? 'badge-success' : 'badge-neutral'}">${escapeHtml(g.status)}</span></td>
                <td class="text-right whitespace-nowrap">
                    <div class="inline-flex items-center gap-1.5">
                        ${g.status === 'active'
                            ? `<button type="button" class="btn-ghost !px-3 !py-1.5 status-btn" data-id="${g.id}" data-status="inactive" data-name="${escapeHtml(g.display_name)}">Deactivate</button>`
                            : `<button type="button" class="btn-ghost !px-3 !py-1.5 status-btn" data-id="${g.id}" data-status="active" data-name="${escapeHtml(g.display_name)}">Activate</button>`}
                        ${g.status === 'active' && !g.is_default
                            ? `<button type="button" class="btn-secondary !px-3 !py-1.5 default-btn" data-id="${g.id}" data-name="${escapeHtml(g.display_name)}">Set default</button>`
                            : ''}
                        <button type="button" class="btn-ghost !px-3 !py-1.5 rotate-btn" data-id="${g.id}" data-name="${escapeHtml(g.display_name)}">Rotate key</button>
                        ${!g.is_default
                            ? `<button type="button" class="btn-icon delete-btn" data-id="${g.id}" data-name="${escapeHtml(g.display_name)}" aria-label="Remove ${escapeHtml(g.display_name)}">${trashIcon}</button>`
                            : ''}
                    </div>
                </td>
            </tr>`).join('');

        wireRowActions();
    }

    function wireRowActions() {
        tbody.querySelectorAll('.status-btn').forEach((btn) => btn.addEventListener('click', async () => {
            setButtonLoading(btn, true);
            const { success, message } = await apiFetch('/api/admin/gateways/update-status.php', {
                method: 'POST', body: { id: btn.dataset.id, status: btn.dataset.status },
            });
            setButtonLoading(btn, false);
            showToast(message, success ? 'success' : 'error');
            if (success) load();
        }));

        tbody.querySelectorAll('.default-btn').forEach((btn) => btn.addEventListener('click', async () => {
            setButtonLoading(btn, true);
            const { success, message } = await apiFetch('/api/admin/gateways/set-default.php', {
                method: 'POST', body: { id: btn.dataset.id },
            });
            setButtonLoading(btn, false);
            showToast(message, success ? 'success' : 'error');
            if (success) load();
        }));

        tbody.querySelectorAll('.rotate-btn').forEach((btn) => btn.addEventListener('click', () => {
            document.getElementById('rotate-key-form').dataset.id = btn.dataset.id;
            document.getElementById('rotate-key-target').textContent = `New key for ${btn.dataset.name}.`;
            document.getElementById('rk-key').value = '';
            openModal('rotate-key-modal');
        }));

        tbody.querySelectorAll('.delete-btn').forEach((btn) => btn.addEventListener('click', () => {
            pendingDeleteId = btn.dataset.id;
            document.getElementById('delete-gateway-body').textContent =
                `${btn.dataset.name} will be permanently removed. This cannot be undone.`;
            openModal('delete-gateway-modal');
        }));
    }

    document.getElementById('add-gateway-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const errorEl = document.getElementById('ag-error');
        errorEl.classList.add('hidden');

        const submitBtn = document.getElementById('ag-submit');
        setButtonLoading(submitBtn, true);
        const { success, message } = await apiFetch('/api/admin/gateways/create.php', {
            method: 'POST',
            body: {
                display_name: document.getElementById('ag-name').value.trim(),
                provider: document.getElementById('ag-provider').value,
                api_key: document.getElementById('ag-key').value.trim(),
            },
        });
        setButtonLoading(submitBtn, false);

        if (!success) {
            errorEl.textContent = message || 'Unable to add this gateway.';
            errorEl.classList.remove('hidden');
            return;
        }
        closeModal('add-gateway-modal');
        e.target.reset();
        showToast(message, 'success');
        load();
    });

    document.getElementById('rotate-key-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const errorEl = document.getElementById('rk-error');
        errorEl.classList.add('hidden');
        const id = e.target.dataset.id;

        const submitBtn = document.getElementById('rk-submit');
        setButtonLoading(submitBtn, true);
        const { success, message } = await apiFetch('/api/admin/gateways/rotate-key.php', {
            method: 'POST',
            body: { id, api_key: document.getElementById('rk-key').value.trim() },
        });
        setButtonLoading(submitBtn, false);

        if (!success) {
            errorEl.textContent = message || 'Unable to rotate this key.';
            errorEl.classList.remove('hidden');
            return;
        }
        closeModal('rotate-key-modal');
        showToast(message, 'success');
        load();
    });

    document.getElementById('delete-gateway-confirm-btn').addEventListener('click', async function () {
        if (!pendingDeleteId) return;
        setButtonLoading(this, true);
        const { success, message } = await apiFetch('/api/admin/gateways/delete.php', {
            method: 'POST', body: { id: pendingDeleteId },
        });
        setButtonLoading(this, false);
        closeModal('delete-gateway-modal');
        showToast(message, success ? 'success' : 'error');
        if (success) load();
    });

    load();
})();
