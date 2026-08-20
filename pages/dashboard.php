<?php
/** Expects $user in scope (set by public/index.php). */
require_once __DIR__ . '/../includes/banner.php';
$isOperator = in_array($user['role'], ['admin', 'operator'], true);
$extraScripts = ['/assets/js/charts.js', '/assets/js/pages/dashboard.js'];

render_hero_banner(
    $user,
    $isOperator ? 'Operations overview' : 'Your dashboard',
    $isOperator ? 'Platform-wide payment activity and operational health.' : 'A snapshot of your balance and recent activity.'
);
?>

<?php
$reportCardMeta = [
    'total' => ['label' => 'Total', 'icon' => 'transactions', 'accent' => 'border-l-brand', 'iconBg' => 'bg-brand-muted', 'iconText' => 'text-brand'],
    'success' => ['label' => 'Successful', 'icon' => 'check-circle', 'accent' => 'border-l-success', 'iconBg' => 'bg-success-bg', 'iconText' => 'text-success'],
    'pending' => ['label' => 'Pending', 'icon' => 'clock', 'accent' => 'border-l-warning', 'iconBg' => 'bg-warning-bg', 'iconText' => 'text-warning'],
    'failed' => ['label' => 'Failed', 'icon' => 'alert-circle', 'accent' => 'border-l-danger', 'iconBg' => 'bg-danger-bg', 'iconText' => 'text-danger'],
];
function render_report_cards(string $prefix, string $type, array $meta): void {
    foreach ($meta as $key => $m) {
        $href = '/transactions?type=' . urlencode($type) . ($key === 'total' ? '' : '&status=' . urlencode($key));
        ?>
        <a href="<?= e($href) ?>" class="card card-interactive !p-5 border-l-4 <?= $m['accent'] ?>">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-9 h-9 rounded-sm <?= $m['iconBg'] ?> <?= $m['iconText'] ?> shrink-0"><?= icon($m['icon'], 'w-5 h-5') ?></span>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-text-secondary truncate"><?= e($m['label']) ?></p>
                    <p class="text-3xl font-semibold text-text-primary" id="<?= e($prefix) ?>-<?= e($key) ?>-amount"><span class="skeleton inline-block h-6 w-20 rounded-sm align-middle"></span></p>
                </div>
            </div>
            <p class="text-sm text-text-secondary mt-2" id="<?= e($prefix) ?>-<?= e($key) ?>-count"></p>
        </a>
    <?php }
}
?>

<div id="dashboard-root" data-role="<?= e($user['role']) ?>">
    <!-- Primary balance panel -->
    <a href="<?= $isOperator ? '/transactions' : '/wallet' ?>" class="card card-interactive !p-6 border-l-4 border-l-brand mb-6" id="primary-balance-card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
                <span class="flex items-center justify-center w-12 h-12 rounded-md bg-brand-muted text-brand shrink-0"><?= icon('wallet', 'w-6 h-6') ?></span>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-text-secondary" id="primary-balance-label"><?= $isOperator ? "Today's transaction volume" : 'Total balance' ?></p>
                    <p class="text-4xl font-semibold text-text-primary" id="primary-balance-amount"><span class="skeleton inline-block h-9 w-40 rounded-sm align-middle"></span></p>
                </div>
            </div>
            <div class="flex items-center gap-6 sm:pl-6 sm:border-l sm:border-border" id="primary-balance-breakdown">
                <div>
                    <p class="text-sm text-text-secondary" id="primary-balance-sub1-label">—</p>
                    <p class="text-lg font-semibold text-text-primary" id="primary-balance-sub1-value">—</p>
                </div>
                <div>
                    <p class="text-sm text-text-secondary" id="primary-balance-sub2-label">—</p>
                    <p class="text-lg font-semibold text-text-primary" id="primary-balance-sub2-value">—</p>
                </div>
                <?= icon('chevron-right', 'w-5 h-5 text-text-secondary hidden sm:block shrink-0') ?>
            </div>
        </div>
    </a>

    <!-- Deposits report -->
    <h2 class="text-3xl font-semibold text-text-primary mb-3">Deposits report</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6" id="deposits-report-grid">
        <?php render_report_cards('dep', 'deposit', $reportCardMeta); ?>
    </div>

    <!-- Withdrawals report -->
    <h2 class="text-3xl font-semibold text-text-primary mb-3">Withdrawals report</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6" id="withdrawals-report-grid">
        <?php render_report_cards('wd', 'withdrawal', $reportCardMeta); ?>
    </div>

    <!-- Analytics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
        <div class="card">
            <h2 class="card-title mb-1">Deposit analytics</h2>
            <p class="card-subtitle mb-4">Successful deposit amount, last 7 days</p>
            <div id="deposits-chart" aria-live="polite">
                <div class="skeleton h-40 w-full rounded-sm"></div>
            </div>
        </div>
        <div class="card">
            <h2 class="card-title mb-1">Withdrawal analytics</h2>
            <p class="card-subtitle mb-4">Successful withdrawal amount, last 7 days</p>
            <div id="withdrawals-chart" aria-live="polite">
                <div class="skeleton h-40 w-full rounded-sm"></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="card-title">Recent activity</h2>
            <a href="/transactions" class="text-sm font-medium text-brand hover:underline">View all transactions</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table-base" id="recent-table">
                <thead>
                    <tr>
                        <?php if ($isOperator): ?><th scope="col">Customer</th><?php endif; ?>
                        <th scope="col">Reference</th>
                        <th scope="col">Type</th>
                        <th scope="col">Amount</th>
                        <th scope="col">Status</th>
                        <th scope="col">Date</th>
                    </tr>
                </thead>
                <tbody id="recent-tbody">
                    <tr><td colspan="6" class="text-center py-6 text-text-secondary">Loading recent activity…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
