<?php
require_once __DIR__ . '/../../includes/banner.php';
$extraScripts = ['/assets/js/pages/admin-kyc-review.js'];

render_hero_banner(
    $user,
    'KYC Review',
    'Verify or reject identity documents submitted by a customer.'
);
?>
<div class="mb-5">
    <a href="/admin/users" class="inline-flex items-center gap-1.5 text-sm text-brand-emphasis hover:underline">
        <?= icon('arrow-left', 'w-4 h-4') ?>
        Back to customers
    </a>
</div>

<div id="kyc-review-error" class="card text-center py-12 hidden">
    <p class="text-3xl font-semibold text-text-primary mb-2">Customer not found</p>
    <p class="text-md text-text-secondary mb-6">Open this page from the Customers list so the right customer is selected.</p>
    <a href="/admin/users" class="btn-primary">Back to customers</a>
</div>

<div id="kyc-review-content" class="hidden">
    <div class="card mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <span id="kyc-review-avatar" class="flex items-center justify-center w-12 h-12 rounded-full bg-brand-muted text-brand-emphasis font-semibold shrink-0"></span>
            <div class="min-w-0">
                <p id="kyc-review-name" class="text-2xl font-semibold text-text-primary truncate"></p>
                <p id="kyc-review-email" class="text-sm text-text-secondary truncate"></p>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0" id="kyc-review-summary-badges"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5" id="kyc-review-grid"></div>
</div>

<div id="kyc-review-loading" class="card text-center py-12">
    <p class="text-md text-text-secondary">Loading customer documents…</p>
</div>
