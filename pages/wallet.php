<?php
/** Customer-only. $user in scope. */
require_once __DIR__ . '/../includes/banner.php';
$extraScripts = ['/assets/js/pages/wallet.js'];

render_hero_banner($user, 'Your wallet', 'Balance, pending funds, and recent wallet activity, all in one place.');
?>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-6">
    <div class="card border-l-4 border-l-brand">
        <div class="flex items-center gap-2.5 mb-2">
            <span class="icon-chip-sm icon-chip-brand"><?= icon('wallet', 'w-4 h-4') ?></span>
            <p class="text-sm font-medium text-text-secondary">Available balance</p>
        </div>
        <p class="text-4xl font-semibold text-text-primary" id="wallet-available"><span class="skeleton inline-block h-8 w-32 rounded-sm align-middle"></span></p>
        <p class="text-sm text-text-secondary mt-1.5">Ready to withdraw or spend</p>
    </div>
    <div class="card border-l-4 border-l-warning">
        <div class="flex items-center gap-2.5 mb-2">
            <span class="icon-chip-sm icon-chip-warning"><?= icon('clock', 'w-4 h-4') ?></span>
            <p class="text-sm font-medium text-text-secondary">Pending balance</p>
        </div>
        <p class="text-4xl font-semibold text-text-primary" id="wallet-pending"><span class="skeleton inline-block h-8 w-32 rounded-sm align-middle"></span></p>
        <p class="text-sm text-text-secondary mt-1.5">Processing, not yet available</p>
    </div>
</div>

<div class="flex flex-wrap items-center gap-3 mb-6">
    <a href="/deposits" class="btn-primary"><?= icon('deposit', 'w-4 h-4') ?>Add funds</a>
    <a href="/withdrawals" class="btn-secondary"><?= icon('withdrawal', 'w-4 h-4') ?>Withdraw</a>
    <a href="/transactions" class="btn-ghost">View all transactions</a>
</div>

<div class="card">
    <h2 class="card-title mb-4">Recent wallet activity</h2>
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead>
                <tr>
                    <th scope="col">Reference</th>
                    <th scope="col">Type</th>
                    <th scope="col">Method</th>
                    <th scope="col">Amount</th>
                    <th scope="col">Status</th>
                    <th scope="col">Date</th>
                </tr>
            </thead>
            <tbody id="wallet-activity-tbody">
                <tr><td colspan="6" class="text-center py-6 text-text-secondary">Loading…</td></tr>
            </tbody>
        </table>
    </div>
</div>
