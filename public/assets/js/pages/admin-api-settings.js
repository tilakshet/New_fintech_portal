(function () {
    'use strict';
    const { apiFetch, showToast, setButtonLoading, escapeHtml } = window.Verapay;

    const loadingEl = document.getElementById('api-settings-loading');
    const contentEl = document.getElementById('api-settings-content');

    function renderSecondaryIps(ips) {
        const list = document.getElementById('as-secondary-ips');
        if (!ips.length) {
            list.innerHTML = '<li class="text-sm text-text-secondary">No secondary IPs added yet.</li>';
            return;
        }
        list.innerHTML = ips.map((row) => `
            <li class="flex items-center justify-between gap-3 rounded-sm border border-border px-3 py-2">
                <span class="font-mono text-sm text-text-primary">${escapeHtml(row.ip_address)}</span>
                <button type="button" class="btn-icon" data-remove-ip="${row.id}" aria-label="Remove ${escapeHtml(row.ip_address)}">×</button>
            </li>`).join('');

        list.querySelectorAll('[data-remove-ip]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                btn.disabled = true;
                const { success, message } = await apiFetch('/api/admin/api-settings/delete-secondary-ip.php', {
                    method: 'POST',
                    body: { id: btn.dataset.removeIp },
                });
                if (!success) {
                    showToast(message || 'Unable to remove that IP.', 'error');
                    btn.disabled = false;
                    return;
                }
                showToast('IP removed.', 'success');
                load();
            });
        });
    }

    async function load() {
        const { success, data, message } = await apiFetch('/api/admin/api-settings/get.php');
        loadingEl.classList.add('hidden');
        contentEl.classList.remove('hidden');

        if (!success) {
            showToast(message || 'Unable to load API settings.', 'error');
            return;
        }

        document.getElementById('as-client-key').value = data.client_key;
        document.getElementById('as-secret-key').value = data.secret_key_masked;
        document.getElementById('as-bearer-token').value = data.bearer_token || '';
        document.getElementById('as-token-meta').textContent = data.bearer_token_generated_at
            ? `Generated ${new Date(data.bearer_token_generated_at).toLocaleString()}`
            : 'No token generated yet.';
        document.getElementById('as-primary-ip').value = data.primary_whitelist_ip || '';
        document.getElementById('as-payout-url').value = data.payout_callback_url || '';
        document.getElementById('as-payin-url').value = data.payin_callback_url || '';
        renderSecondaryIps(data.secondary_ips);
    }

    document.getElementById('as-generate-token').addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const errorEl = document.getElementById('as-token-error');
        errorEl.classList.add('hidden');
        setButtonLoading(btn, true);
        const { success, data, message } = await apiFetch('/api/admin/api-settings/generate-token.php', { method: 'POST' });
        setButtonLoading(btn, false);
        if (!success) {
            errorEl.textContent = message || 'Unable to generate a token.';
            errorEl.classList.remove('hidden');
            return;
        }
        document.getElementById('as-bearer-token').value = data.bearer_token;
        document.getElementById('as-token-meta').textContent = 'Generated just now';
        showToast('Bearer token generated.', 'success');
    });

    document.getElementById('as-save-primary-ip').addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const input = document.getElementById('as-primary-ip');
        const errorEl = document.getElementById('as-primary-ip-error');
        errorEl.classList.add('hidden');
        setButtonLoading(btn, true);
        const { success, message } = await apiFetch('/api/admin/api-settings/update-primary-ip.php', {
            method: 'POST',
            body: { ip_address: input.value.trim() },
        });
        setButtonLoading(btn, false);
        if (!success) {
            errorEl.textContent = message || 'Unable to update the primary IP.';
            errorEl.classList.remove('hidden');
            return;
        }
        showToast('Primary whitelist IP updated.', 'success');
    });

    document.getElementById('as-add-secondary-ip').addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const input = document.getElementById('as-new-secondary-ip');
        const errorEl = document.getElementById('as-secondary-ip-error');
        errorEl.classList.add('hidden');
        if (!input.value.trim()) {
            errorEl.textContent = 'Enter an IP address first.';
            errorEl.classList.remove('hidden');
            return;
        }
        setButtonLoading(btn, true);
        const { success, message } = await apiFetch('/api/admin/api-settings/add-secondary-ip.php', {
            method: 'POST',
            body: { ip_address: input.value.trim() },
        });
        setButtonLoading(btn, false);
        if (!success) {
            errorEl.textContent = message || 'Unable to add that IP.';
            errorEl.classList.remove('hidden');
            return;
        }
        input.value = '';
        showToast('IP added to whitelist.', 'success');
        load();
    });

    document.getElementById('as-save-webhooks').addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const errorEl = document.getElementById('as-webhook-error');
        errorEl.classList.add('hidden');
        setButtonLoading(btn, true);
        const { success, message } = await apiFetch('/api/admin/api-settings/save-webhook.php', {
            method: 'POST',
            body: {
                payout_callback_url: document.getElementById('as-payout-url').value.trim(),
                payin_callback_url: document.getElementById('as-payin-url').value.trim(),
            },
        });
        setButtonLoading(btn, false);
        if (!success) {
            errorEl.textContent = message || 'Unable to save webhook configuration.';
            errorEl.classList.remove('hidden');
            return;
        }
        showToast('Webhook configuration saved.', 'success');
    });

    load();
})();
