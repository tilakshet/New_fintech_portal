<?php
require_once __DIR__ . '/../../includes/modal.php';
$extraScripts = ['/assets/js/pages/admin-gateways.js'];
?>
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <p class="text-md text-text-secondary max-w-2xl">Configure the processors Verapay routes payments through. Exactly one gateway is the default at any time — swap it here when a processor changes without any code changes.</p>
    <button type="button" class="btn-primary shrink-0" data-modal-trigger="add-gateway-modal"><?= icon('plus', 'w-4 h-4') ?>Add gateway</button>
</div>

<div class="card !p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead>
                <tr>
                    <th scope="col">Gateway</th>
                    <th scope="col">Provider</th>
                    <th scope="col">API key</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody id="gateways-tbody">
                <tr><td colspan="5" class="text-center py-8 text-text-secondary">Loading gateways…</td></tr>
            </tbody>
        </table>
    </div>
</div>

<dialog id="add-gateway-modal" class="rounded-md p-0 backdrop:bg-black/40 w-full max-w-md" aria-labelledby="add-gateway-title">
    <form id="add-gateway-form" class="flex flex-col">
        <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-border">
            <h2 id="add-gateway-title" class="text-3xl font-semibold text-text-primary">Add payment gateway</h2>
            <button type="button" class="btn-icon" data-modal-close aria-label="Close dialog"><?= icon('close', 'w-5 h-5') ?></button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <div>
                <label for="ag-name" class="field-label">Display name<span class="text-danger" aria-hidden="true"> *</span></label>
                <input type="text" id="ag-name" class="field-input" maxlength="80" placeholder="e.g. Backup Processor" required>
            </div>
            <div>
                <label for="ag-provider" class="field-label">Provider<span class="text-danger" aria-hidden="true"> *</span></label>
                <select id="ag-provider" class="field-input" required>
                    <option value="">Select a provider…</option>
                    <option value="razorpay">Razorpay</option>
                    <option value="payu">PayU</option>
                    <option value="cashfree">Cashfree</option>
                    <option value="stripe">Stripe</option>
                    <option value="paypal">PayPal</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label for="ag-key" class="field-label">API key<span class="text-danger" aria-hidden="true"> *</span></label>
                <input type="text" id="ag-key" class="field-input font-mono" minlength="8" placeholder="sk_live_…" required autocomplete="off" spellcheck="false">
                <p class="field-help">Stored as a one-way hash. Only the last 4 characters remain visible afterward.</p>
            </div>
            <p id="ag-error" class="field-error hidden"></p>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border bg-surface-muted rounded-b-md">
            <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
            <button type="submit" id="ag-submit" class="btn-primary">Add gateway</button>
        </div>
    </form>
</dialog>

<dialog id="rotate-key-modal" class="rounded-md p-0 backdrop:bg-black/40 w-full max-w-md" aria-labelledby="rotate-key-title">
    <form id="rotate-key-form" class="flex flex-col">
        <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-border">
            <h2 id="rotate-key-title" class="text-3xl font-semibold text-text-primary">Rotate API key</h2>
            <button type="button" class="btn-icon" data-modal-close aria-label="Close dialog"><?= icon('close', 'w-5 h-5') ?></button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <p id="rotate-key-target" class="text-md text-text-secondary"></p>
            <div>
                <label for="rk-key" class="field-label">New API key<span class="text-danger" aria-hidden="true"> *</span></label>
                <input type="text" id="rk-key" class="field-input font-mono" minlength="8" required autocomplete="off" spellcheck="false">
                <p class="field-help">The previous key stops being valid immediately.</p>
            </div>
            <p id="rk-error" class="field-error hidden"></p>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border bg-surface-muted rounded-b-md">
            <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
            <button type="submit" id="rk-submit" class="btn-primary">Rotate key</button>
        </div>
    </form>
</dialog>

<?php
render_modal(
    'delete-gateway-modal',
    'Remove this gateway?',
    '<p id="delete-gateway-body">This gateway configuration will be permanently removed. This cannot be undone.</p>',
    'Remove gateway',
    'id="delete-gateway-confirm-btn"',
    true
);
?>
