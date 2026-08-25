<?php
/** Admin/operator only. $user in scope. */
require_once __DIR__ . '/../../includes/banner.php';
$extraScripts = ['/assets/js/pages/admin-support.js'];

render_hero_banner(
    $user,
    'Support inbox',
    'Customer conversations. Replies are delivered to the customer immediately.'
);
?>
<div class="mb-6">
    <p class="text-md text-text-secondary">Customer conversations. Replies are delivered to the customer immediately.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5" style="min-height: 32rem;">
    <div class="card !p-0 flex flex-col lg:col-span-1">
        <div class="flex items-center justify-between px-5 py-4 border-b border-border">
            <h2 class="card-title">Inbox</h2>
        </div>
        <ul id="conversation-list" class="flex-1 overflow-y-auto divide-y divide-border">
            <li class="px-5 py-6 text-sm text-text-secondary text-center">Loading…</li>
        </ul>
    </div>

    <div class="card !p-0 flex flex-col lg:col-span-2" id="thread-panel">
        <div id="thread-empty" class="flex-1 flex flex-col items-center justify-center text-center px-6 py-16">
            <p class="text-md font-medium text-text-primary mb-1">No conversation selected</p>
            <p class="text-sm text-text-secondary">Choose a conversation from the inbox to reply.</p>
        </div>

        <div id="thread-active" class="hidden flex-1 flex flex-col min-h-0">
            <div class="px-5 py-4 border-b border-border">
                <h2 id="thread-subject" class="text-lg font-semibold text-text-primary"></h2>
                <span id="thread-customer" class="text-sm text-text-secondary"></span>
            </div>
            <div id="thread-messages" class="flex-1 overflow-y-auto px-5 py-4 space-y-4" aria-live="polite"></div>
            <form id="reply-form" class="flex items-end gap-3 px-5 py-4 border-t border-border">
                <div class="flex-1">
                    <label for="reply-message" class="sr-only">Your reply</label>
                    <textarea id="reply-message" name="message" class="field-input" rows="2" maxlength="4000" placeholder="Type your reply…" required></textarea>
                </div>
                <button type="submit" id="reply-submit" class="btn-primary !px-4"><?= icon('send', 'w-4 h-4') ?><span class="sr-only">Send reply</span></button>
            </form>
        </div>
    </div>
</div>
