<?php
$extraScripts = ['/assets/js/pages/settings.js'];
?>
<div class="mb-6">
    <p class="text-md text-text-secondary">Manage your account security.</p>
</div>

<div class="card max-w-xl">
    <h2 class="card-title mb-5">Change password</h2>
    <form id="password-form" novalidate>
        <div class="mb-5">
            <label for="current-password" class="field-label">Current password<span class="text-danger" aria-hidden="true"> *</span></label>
            <input type="password" id="current-password" name="current_password" class="field-input" autocomplete="current-password" required>
        </div>
        <div class="mb-5">
            <label for="new-password" class="field-label">New password<span class="text-danger" aria-hidden="true"> *</span></label>
            <input type="password" id="new-password" name="new_password" class="field-input" autocomplete="new-password" required aria-describedby="new-password-help">
            <p id="new-password-help" class="field-help">At least 10 characters.</p>
        </div>
        <div class="mb-6">
            <label for="confirm-password" class="field-label">Confirm new password<span class="text-danger" aria-hidden="true"> *</span></label>
            <input type="password" id="confirm-password" name="confirm_password" class="field-input" autocomplete="new-password" required>
        </div>
        <p id="password-error" class="field-error hidden mb-4"></p>
        <button type="submit" id="password-submit" class="btn-primary">Update password</button>
    </form>
</div>
