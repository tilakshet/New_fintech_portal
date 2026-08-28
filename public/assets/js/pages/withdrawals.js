(function () {
    'use strict';
    const { apiFetch, showToast, setButtonLoading, escapeHtml, openModal, formatMoney: money } = window.Verapay;

    let availableBalance = 0;
    const amountInput = document.getElementById('w-amount');
    const feeAmount = document.getElementById('w-fee-amount');
    const feeFee = document.getElementById('w-fee-fee');
    const feeTotal = document.getElementById('w-fee-total');

    function calcFee(amount) {
        const fee = amount * 0.01;
        return Math.max(fee, amount > 0 ? 1 : 0);
    }

    function updatePreview() {
        const amount = parseFloat(amountInput.value) || 0;
        const fee = calcFee(amount);
        feeAmount.textContent = money(amount);
        feeFee.textContent = money(fee);
        feeTotal.textContent = money(amount + fee);
    }
    amountInput.addEventListener('input', updatePreview);
    updatePreview();

    async function loadBalance() {
        const { success, data } = await apiFetch('/api/wallet/summary.php');
        availableBalance = success ? parseFloat(data.wallet.available_balance) : 0;
        document.getElementById('current-balance').textContent = success ? money(data.wallet.available_balance) : '—';
    }

    async function loadRecentWithdrawals() {
        const list = document.getElementById('recent-withdrawals-list');
        const { success, data } = await apiFetch('/api/transactions/list.php?type=withdrawal&per_page=5');
        if (!success || !data.transactions.length) {
            list.innerHTML = `<li class="empty-state !py-6">
                <span class="empty-state-icon"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16V8M8.5 12.5 12 9l3.5 3.5"/></svg></span>
                <p class="empty-state-body !mb-0">No withdrawals yet.</p>
            </li>`;
            return;
        }
        list.innerHTML = data.transactions.map((t) => `
            <li class="flex items-center gap-3 -mx-2 px-2 py-1 rounded-sm transition-colors duration-instant hover:bg-surface-muted">
                <span class="icon-chip-sm icon-chip-warning"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16V8M8.5 12.5 12 9l3.5 3.5"/></svg></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-md font-medium text-text-primary truncate">${money(t.amount)}</span>
                    <span class="block text-sm text-text-secondary">${new Date(t.created_at).toLocaleDateString()}</span>
                </span>
                <span class="badge-${t.status === 'success' ? 'success' : t.status === 'pending' ? 'warning' : 'danger'} shrink-0">${escapeHtml(t.status)}</span>
            </li>`).join('');
    }

    document.getElementById('withdrawal-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const amountError = document.getElementById('w-amount-error');
        amountError.classList.add('hidden');
        amountInput.setAttribute('aria-invalid', 'false');

        const amount = amountInput.value.trim();
        const numericAmount = parseFloat(amount);

        if (!/^\d+(\.\d{1,2})?$/.test(amount) || numericAmount < 20) {
            amountError.textContent = 'Enter an amount of at least ₹20.00.';
            amountError.classList.remove('hidden');
            amountInput.setAttribute('aria-invalid', 'true');
            amountInput.focus();
            return;
        }
        const totalNeeded = numericAmount + calcFee(numericAmount);
        if (totalNeeded > availableBalance) {
            amountError.textContent = `Amount plus fee (${money(totalNeeded)}) exceeds your available balance (${money(availableBalance)}).`;
            amountError.classList.remove('hidden');
            amountInput.setAttribute('aria-invalid', 'true');
            amountInput.focus();
            return;
        }

        const submitBtn = document.getElementById('withdrawal-submit');
        setButtonLoading(submitBtn, true);

        const { success, data, message } = await apiFetch('/api/withdrawals/create.php', {
            method: 'POST',
            body: { amount, destination: document.getElementById('w-destination').value },
        });

        setButtonLoading(submitBtn, false);

        if (!success) {
            amountError.textContent = message || 'Unable to process this withdrawal.';
            amountError.classList.remove('hidden');
            showToast(message || 'Withdrawal failed.', 'error');
            return;
        }

        document.getElementById('withdrawal-success-body').textContent =
            `${money(data.amount)} is being sent to your bank account. Reference ${data.reference}. This usually takes 1-2 business days.`;
        openModal('withdrawal-success-modal');

        document.getElementById('withdrawal-form').reset();
        updatePreview();
        loadBalance();
        loadRecentWithdrawals();
    });

    loadBalance();
    loadRecentWithdrawals();
})();
