<?php
/** Customer-only. $user in scope. */
$extraScripts = ['/assets/js/pages/withdrawals.js'];
?>
<div class="mb-6">
    <p class="text-md text-text-secondary">Move funds from your Verapay balance to your bank account.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 card">
        <h2 class="card-title mb-1">New withdrawal</h2>
        <p class="card-subtitle mb-5">Available balance: <span id="current-balance" class="font-semibold text-text-primary">…</span></p>

        <form id="withdrawal-form" novalidate>
            <div class="mb-5">
                <label for="w-amount" class="field-label">Amount<span class="text-danger" aria-hidden="true"> *</span></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-4 flex items-center text-text-secondary" aria-hidden="true">₹</span>
                    <input type="text" inputmode="decimal" id="w-amount" name="amount" class="field-input pl-8" placeholder="0.00" required aria-describedby="w-amount-help w-amount-error">
                </div>
                <p id="w-amount-help" class="field-help">Minimum withdrawal is ₹20.00.</p>
                <p id="w-amount-error" class="field-error hidden"></p>
            </div>

            <div class="mb-6">
                <label for="w-destination" class="field-label">Destination<span class="text-danger" aria-hidden="true"> *</span></label>
                <select id="w-destination" name="destination" class="field-input" required>
                    <option value="HDFC Bank •• 4821">HDFC Bank Savings •• 4821</option>
                </select>
                <p class="field-help">Funds are sent by bank transfer to this account.</p>
            </div>

            <div class="rounded-sm bg-surface-muted px-4 py-3 mb-6 text-md space-y-1.5" aria-live="polite">
                <div class="flex justify-between"><span class="text-text-secondary">Withdrawal amount</span><span id="w-fee-amount" class="text-text-primary font-medium">$0.00</span></div>
                <div class="flex justify-between"><span class="text-text-secondary">Fee (1%, min $1.00)</span><span id="w-fee-fee" class="text-text-primary font-medium">$0.00</span></div>
                <div class="flex justify-between pt-1.5 border-t border-border"><span class="text-text-primary font-semibold">Total deducted</span><span id="w-fee-total" class="text-text-primary font-semibold">$0.00</span></div>
            </div>

            <button type="submit" id="withdrawal-submit" class="btn-primary w-full sm:w-auto"><?= icon('withdrawal', 'w-4 h-4') ?>Confirm withdrawal</button>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title mb-4">Recent withdrawals</h2>
        <ul id="recent-withdrawals-list" class="space-y-3">
            <li class="skeleton h-12 rounded-sm"></li>
            <li class="skeleton h-12 rounded-sm"></li>
            <li class="skeleton h-12 rounded-sm"></li>
        </ul>
    </div>
</div>

<dialog id="withdrawal-success-modal" class="rounded-md p-0 backdrop:bg-black/40 w-full max-w-md" aria-labelledby="withdrawal-success-title">
    <div class="flex flex-col">
        <div class="px-6 py-8 text-center">
            <span class="mx-auto mb-4 flex items-center justify-center w-12 h-12 rounded-full bg-warning-bg text-warning"><?= icon('clock', 'w-6 h-6') ?></span>
            <h2 id="withdrawal-success-title" class="text-3xl font-semibold text-text-primary mb-1.5">Withdrawal submitted</h2>
            <p id="withdrawal-success-body" class="text-md text-text-secondary"></p>
        </div>
        <div class="flex items-center justify-center gap-3 px-6 py-4 border-t border-border bg-surface-muted rounded-b-md">
            <a href="/wallet" class="btn-secondary">View wallet</a>
            <button type="button" class="btn-primary" data-modal-close>Done</button>
        </div>
    </div>
</dialog>
