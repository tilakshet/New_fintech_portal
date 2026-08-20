<?php
require_once __DIR__ . '/../includes/banner.php';
$extraScripts = ['/assets/js/pages/profile.js'];

render_hero_banner($user, 'Your profile', 'Manage your personal information and account security.');
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-1 space-y-5">
        <div class="card text-center">
            <span class="mx-auto mb-4 flex items-center justify-center w-16 h-16 rounded-full bg-brand-muted text-brand-emphasis font-semibold text-3xl"><?= e($user['avatar_initials'] ?? substr($user['name'], 0, 2)) ?></span>
            <p class="text-3xl font-semibold text-text-primary truncate"><?= e($user['name']) ?></p>
            <p class="text-md text-text-secondary truncate"><?= e($user['email']) ?></p>
            <div class="flex items-center justify-center gap-2 mt-3">
                <span class="badge-neutral capitalize"><?= e($user['role']) ?></span>
                <span class="badge-success">Active</span>
            </div>
            <p class="text-sm text-text-secondary mt-4 pt-4 border-t border-border">Member since <?= e(date('M j, Y', strtotime($user['created_at']))) ?></p>
        </div>

        <div class="card">
            <h2 class="card-title mb-3">Security</h2>
            <p class="text-sm text-text-secondary mb-4">Your password was last changed via <a href="/settings" class="text-brand hover:underline font-medium">Settings</a>. We recommend using a unique password you don't reuse elsewhere.</p>
            <a href="/settings" class="btn-secondary w-full justify-center"><?= icon('shield', 'w-4 h-4') ?>Change password</a>
        </div>
    </div>

    <div class="card lg:col-span-2">
        <h2 class="card-title mb-5">Personal information</h2>
        <form id="profile-form" novalidate>
            <div class="mb-5">
                <label for="p-name" class="field-label">Full name<span class="text-danger" aria-hidden="true"> *</span></label>
                <input type="text" id="p-name" name="name" class="field-input" value="<?= e($user['name']) ?>" maxlength="120" required>
                <p id="p-name-error" class="field-error hidden"></p>
            </div>
            <div class="mb-6">
                <label for="p-email" class="field-label">Email address</label>
                <input type="email" id="p-email" class="field-input" value="<?= e($user['email']) ?>" disabled>
                <p class="field-help">Contact support to change the email associated with your account.</p>
            </div>
            <button type="submit" id="profile-submit" class="btn-primary">Save changes</button>
        </form>
    </div>
</div>
