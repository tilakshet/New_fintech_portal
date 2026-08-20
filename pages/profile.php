<?php
$extraScripts = ['/assets/js/pages/profile.js'];
?>
<div class="mb-6">
    <p class="text-md text-text-secondary">Your account details.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="card text-center lg:col-span-1">
        <span class="mx-auto mb-4 flex items-center justify-center w-16 h-16 rounded-full bg-brand-muted text-brand-emphasis font-semibold text-3xl"><?= e($user['avatar_initials'] ?? substr($user['name'], 0, 2)) ?></span>
        <p class="text-3xl font-semibold text-text-primary truncate"><?= e($user['name']) ?></p>
        <p class="text-md text-text-secondary truncate"><?= e($user['email']) ?></p>
        <p class="badge-neutral mt-3 capitalize inline-flex"><?= e($user['role']) ?></p>
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
