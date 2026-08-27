<?php

require_once __DIR__ . '/../../includes/modal.php';
require_once __DIR__ . '/../../includes/banner.php';

$extraScripts = [
    '/assets/js/pages/admin-gateways.js',
    '/assets/js/pages/admin-api-settings.js'
];

render_hero_banner(
    $user,
    'Payment gateways',
    'Configure the processors Verapay routes payments through. Exactly one gateway is the default at any time — swap it here when a processor changes without any code changes.'
);
?>

<div class="mt-8">
    <div id="payment-gateway-tabs"
         class="inline-flex w-full sm:w-auto rounded-md border border-border bg-surface-raised p-1 gap-1"
         role="tablist"
         aria-label="Payment gateway settings">

        <button type="button"
                id="tab-access-token"
                class="btn-primary !px-4 !py-2.5"
                role="tab"
                aria-selected="true"
                aria-controls="access-token-panel">
            <?= icon('key', 'w-4 h-4') ?> Access Token Credentials
        </button>

        <button type="button"
                id="tab-payment-gateway"
                class="btn-secondary !px-4 !py-2.5"
                role="tab"
                aria-selected="false"
                aria-controls="payment-gateway-panel">
            <?= icon('plus', 'w-4 h-4') ?> Add Payment Gateway
        </button>
    </div>
</div>

<!-- Access Token Credentials -->
<div id="access-token-panel"
     role="tabpanel"
     aria-labelledby="tab-access-token">

    <div id="api-settings-loading" class="card text-center py-12 mt-5">
        <p class="text-md text-text-secondary">Loading API settings…</p>
    </div>

    <div id="api-settings-content" class="hidden space-y-5 mt-5">

        <!-- API Credentials -->
        <div class="card">
            <div class="flex items-center gap-2.5 mb-5">
                <span class="flex items-center justify-center w-9 h-9 rounded-md bg-brand/10 text-brand shrink-0">
                    <?= icon('key', 'w-5 h-5') ?>
                </span>
                <h2 class="card-title">API credentials</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
                <div>
                    <label class="field-label">Client key</label>
                    <input type="text"
                           id="as-client-key"
                           class="field-input font-mono"
                           readonly>
                </div>

                <div>
                    <label class="field-label">Secret key</label>
                    <input type="text"
                           id="as-secret-key"
                           class="field-input font-mono"
                           readonly>
                </div>
            </div>

            <label class="field-label">Active bearer token</label>

            <div class="flex flex-col sm:flex-row gap-3">
                <input type="text"
                       id="as-bearer-token"
                       class="field-input font-mono flex-1"
                       readonly
                       placeholder="No token generated yet">

                <button type="button"
                        id="as-generate-token"
                        class="btn-secondary shrink-0">
                    <?= icon('key', 'w-4 h-4') ?> Generate bearer token
                </button>
            </div>

            <p id="as-token-meta" class="field-help"></p>
            <p id="as-token-error" class="field-error hidden"></p>
        </div>

        <!-- IP Whitelisting -->
        <div class="card">
            <div class="flex items-center gap-2.5 mb-5">
                <span class="flex items-center justify-center w-9 h-9 rounded-md bg-brand/10 text-brand shrink-0">
                    <?= icon('shield', 'w-5 h-5') ?>
                </span>
                <h2 class="card-title">IP whitelisting</h2>
            </div>

            <div class="mb-5">
                <label for="as-primary-ip" class="field-label">
                    Primary whitelist IP
                </label>

                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="text"
                           id="as-primary-ip"
                           class="field-input flex-1"
                           placeholder="e.g. 13.203.91.156">

                    <button type="button"
                            id="as-save-primary-ip"
                            class="btn-secondary shrink-0">
                        Update IP
                    </button>
                </div>

                <p id="as-primary-ip-error" class="field-error hidden"></p>
            </div>

            <div>
                <label class="field-label">Secondary IPs</label>

                <ul id="as-secondary-ips" class="space-y-2 mb-3"></ul>

                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="text"
                           id="as-new-secondary-ip"
                           class="field-input flex-1"
                           placeholder="Add another IP address">

                    <button type="button"
                            id="as-add-secondary-ip"
                            class="btn-secondary shrink-0">
                        <?= icon('plus', 'w-4 h-4') ?> Add IP
                    </button>
                </div>

                <p id="as-secondary-ip-error" class="field-error hidden"></p>
            </div>
        </div>

        <!-- Webhook Callbacks -->
        <div class="card">
            <div class="flex items-center gap-2.5 mb-5">
                <span class="flex items-center justify-center w-9 h-9 rounded-md bg-brand/10 text-brand shrink-0">
                    <?= icon('send', 'w-5 h-5') ?>
                </span>
                <h2 class="card-title">Webhook callbacks</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
                <div>
                    <label for="as-payout-url" class="field-label">
                        Payout callback URL
                    </label>

                    <input type="url"
                           id="as-payout-url"
                           class="field-input"
                           placeholder="https://…">
                </div>

                <div>
                    <label for="as-payin-url" class="field-label">
                        Pay-in callback URL
                    </label>

                    <input type="url"
                           id="as-payin-url"
                           class="field-input"
                           placeholder="https://…">
                </div>
            </div>

            <p id="as-webhook-error" class="field-error hidden mb-4"></p>

            <button type="button"
                    id="as-save-webhooks"
                    class="btn-primary">
                <?= icon('upload', 'w-4 h-4') ?> Save webhook configurations
            </button>
        </div>

    </div>
</div>

<!-- Payment Gateway -->
<div id="payment-gateway-panel"
     class="hidden"
     role="tabpanel"
     aria-labelledby="tab-payment-gateway">

    <div class="flex justify-end mt-5 mb-6">
        <button type="button"
                class="btn-primary shrink-0"
                data-modal-trigger="add-gateway-modal">
            <?= icon('plus', 'w-4 h-4') ?> Add gateway
        </button>
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
                        <th scope="col">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody id="gateways-tbody">
                    <tr>
                        <td colspan="5"
                            class="text-center py-8 text-text-secondary">
                            Loading gateways…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Gateway Modal -->
<dialog id="add-gateway-modal"
        class="rounded-md p-0 backdrop:bg-black/40 w-full max-w-md"
        aria-labelledby="add-gateway-title">

    <form id="add-gateway-form" class="flex flex-col">

        <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-border">
            <h2 id="add-gateway-title"
                class="text-3xl font-semibold text-text-primary">
                Add payment gateway
            </h2>

            <button type="button"
                    class="btn-icon"
                    data-modal-close
                    aria-label="Close dialog">
                <?= icon('close', 'w-5 h-5') ?>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">

            <div>
                <label for="ag-name" class="field-label">
                    Display name
                    <span class="text-danger" aria-hidden="true">*</span>
                </label>

                <input type="text"
                       id="ag-name"
                       class="field-input"
                       maxlength="80"
                       placeholder="e.g. Backup Processor"
                       required>
            </div>

            <div>
                <label for="ag-provider" class="field-label">
                    Provider
                    <span class="text-danger" aria-hidden="true">*</span>
                </label>

                <select id="ag-provider"
                        class="field-input"
                        required>
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
                <label for="ag-key" class="field-label">
                    API key
                    <span class="text-danger" aria-hidden="true">*</span>
                </label>

                <input type="text"
                       id="ag-key"
                       class="field-input font-mono"
                       minlength="8"
                       placeholder="sk_live_…"
                       required
                       autocomplete="off"
                       spellcheck="false">

                <p class="field-help">
                    Stored as a one-way hash. Only the last 4 characters remain visible afterward.
                </p>
            </div>

            <p id="ag-error" class="field-error hidden"></p>
        </div>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border bg-surface-muted rounded-b-md">
            <button type="button"
                    class="btn-secondary"
                    data-modal-close>
                Cancel
            </button>

            <button type="submit"
                    id="ag-submit"
                    class="btn-primary">
                Add gateway
            </button>
        </div>
    </form>
</dialog>

<!-- Rotate API Key Modal -->
<dialog id="rotate-key-modal"
        class="rounded-md p-0 backdrop:bg-black/40 w-full max-w-md"
        aria-labelledby="rotate-key-title">

    <form id="rotate-key-form" class="flex flex-col">

        <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-border">
            <h2 id="rotate-key-title"
                class="text-3xl font-semibold text-text-primary">
                Rotate API key
            </h2>

            <button type="button"
                    class="btn-icon"
                    data-modal-close
                    aria-label="Close dialog">
                <?= icon('close', 'w-5 h-5') ?>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">

            <p id="rotate-key-target"
               class="text-md text-text-secondary"></p>

            <div>
                <label for="rk-key" class="field-label">
                    New API key
                    <span class="text-danger" aria-hidden="true">*</span>
                </label>

                <input type="text"
                       id="rk-key"
                       class="field-input font-mono"
                       minlength="8"
                       required
                       autocomplete="off"
                       spellcheck="false">

                <p class="field-help">
                    The previous key stops being valid immediately.
                </p>
            </div>

            <p id="rk-error" class="field-error hidden"></p>
        </div>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border bg-surface-muted rounded-b-md">
            <button type="button"
                    class="btn-secondary"
                    data-modal-close>
                Cancel
            </button>

            <button type="submit"
                    id="rk-submit"
                    class="btn-primary">
                Rotate key
            </button>
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