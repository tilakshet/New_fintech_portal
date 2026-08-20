(function () {
    'use strict';
    const { apiFetch, showToast, setButtonLoading, escapeHtml, openModal, formatMoney: money } = window.Verapay;

    const amountInput = document.getElementById('amount');
    const methodInputs = document.querySelectorAll('input[name="method"]');
    const feeAmount = document.getElementById('fee-amount');
    const feeFee = document.getElementById('fee-fee');
    const feeNet = document.getElementById('fee-net');

    function selectedMethod() {
        return document.querySelector('input[name="method"]:checked')?.value || 'Bank transfer';
    }

    function updatePreview() {
        const amount = parseFloat(amountInput.value) || 0;
        const fee = selectedMethod() === 'Debit card' ? amount * 0.025 : 0;
        feeAmount.textContent = money(amount);
        feeFee.textContent = money(fee);
        feeNet.textContent = money(amount - fee);
    }
    amountInput.addEventListener('input', updatePreview);
    methodInputs.forEach((el) => el.addEventListener('change', updatePreview));
    updatePreview();

    async function loadBalance() {
        const { success, data } = await apiFetch('/api/wallet/summary.php');
        document.getElementById('current-balance').textContent = success ? money(data.wallet.available_balance) : '—';
    }

    async function loadRecentDeposits() {
        const list = document.getElementById('recent-deposits-list');
        const { success, data } = await apiFetch('/api/transactions/list.php?type=deposit&per_page=5');
        if (!success || !data.transactions.length) {
            list.innerHTML = '<li class="text-sm text-text-secondary">No deposits yet.</li>';
            return;
        }
        list.innerHTML = data.transactions.map((t) => `
            <li class="flex items-center justify-between gap-3 -mx-2 px-2 py-1 rounded-sm transition-colors duration-instant hover:bg-surface-muted">
                <span class="min-w-0">
                    <span class="block text-md font-medium text-text-primary truncate">${money(t.amount)}</span>
                    <span class="block text-sm text-text-secondary">${escapeHtml(t.method)} · ${new Date(t.created_at).toLocaleDateString()}</span>
                </span>
                <span class="badge-${t.status === 'success' ? 'success' : t.status === 'pending' ? 'warning' : 'danger'} shrink-0">${escapeHtml(t.status)}</span>
            </li>`).join('');
    }

    document.getElementById('deposit-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const amountError = document.getElementById('amount-error');
        amountError.classList.add('hidden');
        amountInput.setAttribute('aria-invalid', 'false');

        const amount = amountInput.value.trim();
        if (!/^\d+(\.\d{1,2})?$/.test(amount) || parseFloat(amount) < 10) {
            amountError.textContent = 'Enter an amount of at least ₹10.00.';
            amountError.classList.remove('hidden');
            amountInput.setAttribute('aria-invalid', 'true');
            amountInput.focus();
            return;
        }

        const submitBtn = document.getElementById('deposit-submit');
        setButtonLoading(submitBtn, true);

        const { success, data, message } = await apiFetch('/api/deposits/create.php', {
            method: 'POST',
            body: { amount, method: selectedMethod() },
        });

        setButtonLoading(submitBtn, false);

        if (!success) {
            amountError.textContent = message || 'Unable to process this deposit.';
            amountError.classList.remove('hidden');
            showToast(message || 'Deposit failed.', 'error');
            return;
        }

        document.getElementById('deposit-success-body').textContent = data.status === 'success'
            ? `${money(data.net_amount)} was added to your available balance. Reference ${data.reference}.`
            : `${money(data.net_amount)} will be added once your transfer settles (1-2 business days). Reference ${data.reference}.`;
        openModal('deposit-success-modal');

        document.getElementById('deposit-form').reset();
        updatePreview();
        loadBalance();
        loadRecentDeposits();
    });

    loadBalance();
    loadRecentDeposits();
})();
