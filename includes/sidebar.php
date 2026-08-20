<?php
/**
 * Expects $user (array) and $route (string) in scope, set by public/index.php.
 */
require_once __DIR__ . '/../public/assets/icons/icons.php';

$customerNav = [
    ['dashboard', 'dashboard', 'Dashboard'],
    ['wallet', 'wallet', 'Wallet'],
    ['deposits', 'deposit', 'Deposits'],
    ['withdrawals', 'withdrawal', 'Withdrawals'],
    ['transactions', 'transactions', 'Transactions'],
    ['support', 'support', 'Support'],
    ['notifications', 'notification', 'Notifications'],
];

$adminNav = [
    ['dashboard', 'dashboard', 'Dashboard'],
    ['transactions', 'transactions', 'Transactions'],
    ['admin/users', 'users', 'Customers'],
    ['admin/gateways', 'gateway', 'Payment gateways'],
    ['admin/support', 'support', 'Support inbox'],
    ['admin/audit-log', 'shield', 'Audit log'],
    ['notifications', 'notification', 'Notifications'],
];

$navItems = in_array($user['role'], ['admin', 'operator'], true) ? $adminNav : $customerNav;
$footerNav = [
    ['profile', 'profile', 'Profile'],
    ['settings', 'settings', 'Settings'],
];
?>
<aside id="app-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full lg:translate-x-0 transition-transform duration-fast ease-in-out bg-surface-strong flex flex-col" aria-label="Primary">
    <div class="flex items-center gap-2.5 px-6 py-6">
        <span class="flex items-center justify-center w-8 h-8 rounded-sm bg-brand text-white font-bold text-md" aria-hidden="true">V</span>
        <span class="text-2xl font-bold text-text-inverse tracking-tight">Verapay</span>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 space-y-0.5" aria-label="Main navigation">
        <?php foreach ($navItems as [$routeKey, $iconName, $label]): ?>
            <a href="/<?= e($routeKey) ?>" class="nav-link" <?= $route === $routeKey ? 'aria-current="page"' : '' ?>>
                <span class="nav-link-indicator" aria-hidden="true"></span>
                <?= icon($iconName, 'w-5 h-5 shrink-0') ?>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="px-3 py-3 border-t border-white/10 space-y-0.5">
        <?php foreach ($footerNav as [$routeKey, $iconName, $label]): ?>
            <a href="/<?= e($routeKey) ?>" class="nav-link" <?= $route === $routeKey ? 'aria-current="page"' : '' ?>>
                <span class="nav-link-indicator" aria-hidden="true"></span>
                <?= icon($iconName, 'w-5 h-5 shrink-0') ?>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
        <form action="/api/auth/logout.php" method="post" data-confirm-off>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="nav-link w-full text-left">
                <span class="nav-link-indicator" aria-hidden="true"></span>
                <?= icon('logout', 'w-5 h-5 shrink-0') ?>
                <span>Log out</span>
            </button>
        </form>
    </div>
</aside>
<button type="button" id="sidebar-backdrop" class="fixed inset-0 bg-black/40 z-30 hidden lg:hidden" aria-hidden="true" tabindex="-1"></button>
