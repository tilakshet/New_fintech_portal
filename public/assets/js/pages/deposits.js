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
            list.innerHTML = `<li class="empty-state !py-6">
                <span class="empty-state-icon"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8.5 11.5 12 15l3.5-3.5"/></svg></span>
                <p class="empty-state-body !mb-0">No deposits yet.</p>
            </li>`;
            return;
        }
        list.innerHTML = data.transactions.map((t) => `
            <li class="flex items-center gap-3 -mx-2 px-2 py-1 rounded-sm transition-colors duration-instant hover:bg-surface-muted">
                <span class="icon-chip-sm icon-chip-success"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8.5 11.5 12 15l3.5-3.5"/></svg></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-md font-medium text-text-primary truncate">${money(t.amount)}</span>
                    <span class="block text-sm text-text-secondary">${escapeHtml(t.method)} · ${new Date(t.created_at).toLocaleDateString()}</span>
                </span>
                <span class="badge-${t.status === 'success' ? 'success' : t.status === 'pending' ? 'warning' : 'danger'} shrink-0">${escapeHtml(t.status)}</span>
            </li>`).join('');
    }

    // Lazy-loaded only when a deposit actually routes through a live
    // Razorpay-integrated gateway — most deposits (any other gateway, or
    // one without real credentials configured) never touch this.
    let razorpayScriptPromise = null;
    function loadRazorpayCheckoutScript() {
        if (razorpayScriptPromise) return razorpayScriptPromise;
        razorpayScriptPromise = new Promise((resolve, reject) => {
            if (window.Razorpay) { resolve(); return; }
            const script = document.createElement('script');
            script.src = 'https://checkout.razorpay.com/v1/checkout.js';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Could not load the payment widget.'));
            document.head.appendChild(script);
        });
        return razorpayScriptPromise;
    }

    async function launchRazorpayCheckout(checkout, reference) {
        try {
            await loadRazorpayCheckoutScript();
        } catch (err) {
            showToast('Could not load the payment window. Please try again.', 'error');
            return;
        }

        const rzp = new window.Razorpay({
            key: checkout.key_id,
            amount: checkout.amount,
            currency: checkout.currency,
            order_id: checkout.order_id,
            name: 'Verapay',
            description: `Wallet deposit · ${reference}`,
            handler: function () {
                // Only an in-progress signal — the wallet is credited by the
                // webhook once the gateway confirms, never from this callback.
                showToast('Payment received — confirming with the gateway.', 'success');
                loadBalance();
                loadRecentDeposits();
            },
            modal: {
                ondismiss: function () {
                    showToast('Payment window closed. This deposit is still pending — check Transactions or try again.', 'info');
                },
            },
        });

        rzp.on('payment.failed', function () {
            showToast('The gateway declined this payment. This deposit will show as failed shortly.', 'error');
        });

        rzp.open();
    }

    // Same lazy-load pattern as Razorpay above, for Cashfree's Drop-in
    // Checkout widget - only loaded when a deposit actually routes
    // through a live Cashfree-integrated gateway.
    let cashfreeScriptPromise = null;
    function loadCashfreeCheckoutScript() {
        if (cashfreeScriptPromise) return cashfreeScriptPromise;
        cashfreeScriptPromise = new Promise((resolve, reject) => {
            if (window.Cashfree) { resolve(); return; }
            const script = document.createElement('script');
            script.src = 'https://sdk.cashfree.com/js/v3/cashfree.js';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Could not load the payment widget.'));
            document.head.appendChild(script);
        });
        return cashfreeScriptPromise;
    }

    async function launchCashfreeCheckout(checkout) {
        try {
            await loadCashfreeCheckoutScript();
        } catch (err) {
            showToast('Could not load the payment window. Please try again.', 'error');
            return;
        }

        const cashfree = window.Cashfree({ mode: checkout.environment });

        // Modal target keeps the customer on this page - the wallet is
        // credited by the webhook once the gateway confirms, never from
        // this call's return value.
        cashfree.checkout({
            paymentSessionId: checkout.payment_session_id,
            redirectTarget: '_modal',
        }).then(() => {
            showToast('Payment window closed — confirming with the gateway.', 'success');
            loadBalance();
            loadRecentDeposits();
        });
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

        document.getElementById('deposit-form').reset();
        updatePreview();
        loadRecentDeposits();

        if (data.checkout && data.checkout.provider === 'razorpay') {
            launchRazorpayCheckout(data.checkout, data.reference);
            return;
        }
        if (data.checkout && data.checkout.provider === 'cashfree') {
            launchCashfreeCheckout(data.checkout);
            return;
        }

        document.getElementById('deposit-success-body').textContent = data.status === 'success'
            ? `${money(data.net_amount)} was added to your available balance. Reference ${data.reference}.`
            : `${money(data.net_amount)} will be added once your transfer settles (1-2 business days). Reference ${data.reference}.`;
        openModal('deposit-success-modal');

        loadBalance();
    });

    loadBalance();
    loadRecentDeposits();
})();
