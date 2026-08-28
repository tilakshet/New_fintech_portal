<?php
/** Customer-only. $user in scope. */
require_once __DIR__ . '/../includes/banner.php';
$extraScripts = ['/assets/js/pages/deposits.js'];

render_hero_banner(
    $user,
    'Add funds',
    'Add money to your Verapay balance in a few seconds.'
);
?>
<div class="mb-6">
    <p class="text-md text-text-secondary">Add funds to your Verapay balance. This is an action — for your full balance overview, see <a href="/wallet" class="text-brand hover:underline font-medium">Wallet</a>.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 card border-l-4 border-l-success">
        <div class="flex items-center gap-2.5 mb-1">
            <span class="icon-chip-md icon-chip-success"><?= icon('deposit', 'w-4 h-4') ?></span>
            <h2 class="card-title">New deposit</h2>
        </div>
        <p class="card-subtitle mb-5">Current available balance: <span id="current-balance" class="font-semibold text-text-primary">…</span></p>

        <form id="deposit-form" novalidate>
            <div class="mb-5">
                <label for="amount" class="field-label">Amount<span class="text-danger" aria-hidden="true"> *</span></label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-4 flex items-center text-text-secondary" aria-hidden="true">₹</span>
                    <input type="text" inputmode="decimal" id="amount" name="amount" class="field-input pl-8" placeholder="0.00" required aria-describedby="amount-help amount-error">
                </div>
                <p id="amount-help" class="field-help">Minimum deposit is ₹10.00. Maximum ₹50,000.00 per transaction.</p>
                <p id="amount-error" class="field-error hidden"></p>
            </div>

            <fieldset class="mb-6">
                <legend class="field-label">Payment method<span class="text-danger" aria-hidden="true"> *</span></legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-3 rounded-sm border border-border-strong px-4 py-3 cursor-pointer has-[:checked]:border-brand has-[:checked]:bg-brand-muted/40">
                        <input type="radio" name="method" value="Bank transfer" class="mt-1" checked>
                        <span>
                            <span class="block text-md font-semibold text-text-primary">Bank transfer</span>
                            <span class="block text-sm text-text-secondary">No fee · settles in 1-2 business days</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-sm border border-border-strong px-4 py-3 cursor-pointer has-[:checked]:border-brand has-[:checked]:bg-brand-muted/40">
                        <input type="radio" name="method" value="Debit card" class="mt-1">
                        <span>
                            <span class="block text-md font-semibold text-text-primary">Debit card</span>
                            <span class="block text-sm text-text-secondary">2.5% fee · instant</span>
                        </span>
                    </label>
                </div>
            </fieldset>

            <div class="rounded-sm bg-surface-muted px-4 py-3 mb-6 text-md space-y-1.5" id="fee-summary" aria-live="polite">
                <div class="flex justify-between"><span class="text-text-secondary">Deposit amount</span><span id="fee-amount" class="text-text-primary font-medium">$0.00</span></div>
                <div class="flex justify-between"><span class="text-text-secondary">Fee</span><span id="fee-fee" class="text-text-primary font-medium">$0.00</span></div>
                <div class="flex justify-between pt-1.5 border-t border-border"><span class="text-text-primary font-semibold">You'll receive</span><span id="fee-net" class="text-text-primary font-semibold">$0.00</span></div>
            </div>

            <button type="submit" id="deposit-submit" class="btn-primary w-full sm:w-auto"><?= icon('deposit', 'w-4 h-4') ?>Confirm deposit</button>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title mb-4">Recent deposits</h2>
        <ul id="recent-deposits-list" class="space-y-3">
            <li class="skeleton h-12 rounded-sm"></li>
            <li class="skeleton h-12 rounded-sm"></li>
            <li class="skeleton h-12 rounded-sm"></li>
        </ul>
    </div>
</div>

<dialog id="deposit-success-modal" class="rounded-md p-0 backdrop:bg-black/40 w-full max-w-md" aria-labelledby="deposit-success-title">
    <div class="flex flex-col">
        <div class="px-6 py-8 text-center">
            <span class="icon-chip-lg icon-chip-success !rounded-full mx-auto mb-4"><?= icon('check-circle', 'w-6 h-6') ?></span>
            <h2 id="deposit-success-title" class="text-3xl font-semibold text-text-primary mb-1.5">Deposit submitted</h2>
            <p id="deposit-success-body" class="text-md text-text-secondary"></p>
        </div>
        <div class="flex items-center justify-center gap-3 px-6 py-4 border-t border-border bg-surface-muted rounded-b-md">
            <a href="/wallet" class="btn-secondary">View wallet</a>
            <button type="button" class="btn-primary" data-modal-close>Done</button>
        </div>
    </div>
</dialog>
