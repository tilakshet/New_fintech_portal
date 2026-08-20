<?php
$extraScripts = ['/assets/js/pages/notifications.js'];
?>
<div class="mb-6 flex items-center justify-between gap-4">
    <p class="text-md text-text-secondary">Account, payment, and security updates.</p>
    <button type="button" id="mark-all-read" class="btn-tertiary">Mark all as read</button>
</div>

<div class="card !p-0">
    <ul id="notifications-list" class="divide-y divide-border">
        <li class="px-5 py-6 text-sm text-text-secondary text-center">Loading…</li>
    </ul>
</div>
