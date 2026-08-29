<?php

require_once __DIR__ . '/../../includes/modal.php';
require_once __DIR__ . '/../../includes/banner.php';

$extraScripts = ['/assets/js/pages/admin-users.js'];

render_hero_banner(
    $user,
    'Customers',
    'Search customers, add new customers, review their status, and suspend or reactivate accounts.'
);
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <p class="text-md text-text-secondary">
        Search customers, add new customers, review their status, and suspend or reactivate accounts.
    </p>

    <button type="button"
            class="btn-primary shrink-0"
            data-modal-trigger="add-customer-modal">
        <?= icon('plus', 'w-4 h-4') ?> Add customer
    </button>
</div>

<div class="card mb-5">
    <form id="filters-form" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
        <div class="sm:col-span-2">
            <label for="f-search" class="field-label">
                <?= icon('search', 'w-3.5 h-3.5 inline -mt-0.5 mr-1') ?>
                Search name or email
            </label>

            <input type="search"
                   id="f-search"
                   name="search"
                   class="field-input"
                   placeholder="Priya, priya@verapay.test">
        </div>

        <div>
            <label for="f-status" class="field-label">Status</label>

            <select id="f-status"
                    name="status"
                    class="field-input">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
            </select>
        </div>
    </form>
</div>

<div class="card !p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead>
                <tr>
                    <th scope="col">Customer</th>
                    <th scope="col">Role</th>
                    <th scope="col">Status</th>
                    <th scope="col">Joined</th>
                    <th scope="col">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>

            <tbody id="users-tbody">
                <tr>
                    <td colspan="5"
                        class="text-center py-8 text-text-secondary">
                        Loading customers…
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-4 border-t border-border"
         id="users-pagination">
    </div>
</div>

<!-- Add Customer Modal -->
<dialog id="add-customer-modal"
        class="rounded-md p-0 backdrop:bg-black/40 w-full max-w-md"
        aria-labelledby="add-customer-title">

    <form id="add-customer-form" class="flex flex-col">

        <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-border">
            <h2 id="add-customer-title"
                class="text-3xl font-semibold text-text-primary">
                Add customer
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
                <label for="ac-name" class="field-label">
                    Full name
                    <span class="text-danger" aria-hidden="true">*</span>
                </label>

                <input type="text"
                       id="ac-name"
                       class="field-input"
                       maxlength="120"
                       placeholder="e.g. Akash Bhamare"
                       required
                       autocomplete="name">
            </div>

            <div>
                <label for="ac-email" class="field-label">
                    Email address
                    <span class="text-danger" aria-hidden="true">*</span>
                </label>

                <input type="email"
                       id="ac-email"
                       class="field-input"
                       maxlength="190"
                       placeholder="customer@example.com"
                       required
                       autocomplete="email">
            </div>

            <div>
                <label for="ac-password" class="field-label">
                    Temporary password
                    <span class="text-danger" aria-hidden="true">*</span>
                </label>

                <input type="password"
                       id="ac-password"
                       class="field-input"
                       minlength="8"
                       maxlength="72"
                       placeholder="Minimum 8 characters"
                       required
                       autocomplete="new-password">

                <p class="field-help">
                    The customer can use this password to sign in. The password is stored securely as a hash.
                </p>
            </div>

            <div>
                <label for="ac-gender" class="field-label">Gender</label>

                <select id="ac-gender"
                        class="field-input">
                    <option value="">Prefer not to say</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <p id="ac-error"
               class="field-error hidden">
            </p>
        </div>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border bg-surface-muted rounded-b-md">
            <button type="button"
                    class="btn-secondary"
                    data-modal-close>
                Cancel
            </button>

            <button type="submit"
                    id="ac-submit"
                    class="btn-primary">
                Add customer
            </button>
        </div>
    </form>
</dialog>

<?php

render_modal(
    'suspend-modal',
    'Suspend this account?',
    '<p id="suspend-modal-body">This customer will be signed out immediately and unable to access Verapay until reactivated.</p>',
    'Suspend account',
    'id="suspend-confirm-btn"',
    true
);

render_modal(
    'reactivate-modal',
    'Reactivate this account?',
    '<p id="reactivate-modal-body">This customer will regain access to Verapay and can sign in again.</p>',
    'Reactivate account',
    'id="reactivate-confirm-btn"'
);

?>

<!-- API Access Modal -->
<dialog id="api-access-modal"
        class="rounded-md p-0 backdrop:bg-black/40 w-full max-w-lg"
        aria-labelledby="api-access-title">

    <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-border">
        <h2 id="api-access-title" class="text-3xl font-semibold text-text-primary">API Access</h2>

        <button type="button" class="btn-icon" data-modal-close aria-label="Close dialog">
            <?= icon('close', 'w-5 h-5') ?>
        </button>
    </div>

    <div class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">

        <p id="api-access-error" class="field-error hidden"></p>

        <div>
            <p class="field-label mb-2">Status</p>
            <p id="api-access-status"></p>
        </div>

        <div id="api-access-token-reveal" class="hidden bg-warning-bg border border-warning/30 rounded-md p-4 space-y-2">
            <p class="text-sm font-medium text-text-primary">Copy this API key now — it will not be shown again.</p>
            <code id="api-access-token-value" class="block text-sm font-mono break-all bg-surface rounded px-3 py-2"></code>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" id="api-access-generate-btn" class="btn-primary">
                Generate API key
            </button>

            <button type="button" id="api-access-revoke-btn" class="btn-danger hidden">
                Revoke access
            </button>
        </div>

        <form id="api-access-settings-form" class="space-y-4 pt-4 border-t border-border hidden">

            <div>
                <label for="api-access-payin" class="field-label">Pay-in callback URL</label>
                <input type="url" id="api-access-payin" class="field-input" placeholder="https://client.example.com/webhooks/payin">
                <p class="field-help">Verapay notifies this URL when a deposit this client created settles.</p>
            </div>

            <div>
                <label for="api-access-payout" class="field-label">Payout callback URL</label>
                <input type="url" id="api-access-payout" class="field-input" placeholder="https://client.example.com/webhooks/payout">
            </div>

            <div>
                <label for="api-access-ips" class="field-label">IP whitelist</label>
                <textarea id="api-access-ips" class="field-input font-mono" rows="3" placeholder="One IP address per line. Leave blank to allow any IP."></textarea>
                <p class="field-help">If this list is empty, requests are accepted from any IP — add at least one to restrict access.</p>
            </div>

            <div class="flex justify-end">
                <button type="submit" id="api-access-settings-submit" class="btn-secondary">
                    Save settings
                </button>
            </div>
        </form>
<!-- API Access (IP whitelist) Modal -->
<dialog id="api-ips-modal"
        class="rounded-md p-0 backdrop:bg-black/40 w-full max-w-lg"
        aria-labelledby="api-ips-title">

    <div class="flex flex-col">
        <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-border">
            <div>
                <h2 id="api-ips-title" class="text-3xl font-semibold text-text-primary">API access</h2>
                <p id="api-ips-target" class="text-sm text-text-secondary mt-0.5"></p>
            </div>
            <button type="button" class="btn-icon shrink-0" data-modal-close aria-label="Close dialog">
                <?= icon('close', 'w-5 h-5') ?>
            </button>
        </div>

        <div class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">
            <div id="api-ips-no-creds" class="hidden text-center py-6">
                <span class="icon-chip-md icon-chip-neutral mx-auto mb-3"><?= icon('key', 'w-4 h-4') ?></span>
                <p class="text-md text-text-primary font-medium mb-1">No API credentials yet</p>
                <p class="text-sm text-text-secondary">This customer hasn't generated their own client key from Settings — there's nothing to whitelist until they do.</p>
            </div>

            <div id="api-ips-body">

                <!-- Credentials status -->
                <div class="rounded-md border border-border overflow-hidden mb-5">
                    <div class="flex items-center gap-2.5 px-4 py-3 bg-surface-muted border-b border-border">
                        <span class="icon-chip-sm icon-chip-brand"><?= icon('key', 'w-3.5 h-3.5') ?></span>
                        <h3 class="text-md font-semibold text-text-primary">Credentials</h3>
                    </div>

                    <dl class="divide-y divide-border">
                        <div class="flex items-center justify-between gap-4 px-4 py-2.5">
                            <dt class="text-sm text-text-secondary shrink-0">Client key</dt>
                            <dd id="api-ips-client-key" class="font-mono text-sm text-text-primary text-right truncate"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-4 py-2.5">
                            <dt class="text-sm text-text-secondary shrink-0">Bearer token</dt>
                            <dd id="api-ips-token-status" class="text-right"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-4 py-2.5">
                            <dt class="text-sm text-text-secondary shrink-0">Pay-in callback</dt>
                            <dd id="api-ips-payin-url" class="text-sm text-text-primary text-right truncate max-w-[60%]"></dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-4 py-2.5">
                            <dt class="text-sm text-text-secondary shrink-0">Payout callback</dt>
                            <dd id="api-ips-payout-url" class="text-sm text-text-primary text-right truncate max-w-[60%]"></dd>
                        </div>
                    </dl>
                </div>

                <!-- Whitelisted IPs -->
                <div>
                    <div class="flex items-center gap-2.5 mb-1">
                        <span class="icon-chip-sm icon-chip-brand"><?= icon('shield', 'w-3.5 h-3.5') ?></span>
                        <h3 class="text-md font-semibold text-text-primary">Whitelisted IPs</h3>
                    </div>
                    <p class="field-help mb-3">Only requests from these addresses can use this customer's API token.</p>

                    <ul id="api-ips-list" class="space-y-2 mb-4"></ul>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <input type="text" id="api-ips-new" class="field-input flex-1" placeholder="e.g. 13.203.91.156">
                        <button type="button" id="api-ips-add" class="btn-secondary shrink-0"><?= icon('plus', 'w-4 h-4') ?> Add IP</button>
                    </div>
                    <p id="api-ips-error" class="field-error hidden mt-2"></p>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border bg-surface-muted rounded-b-md">
            <button type="button" class="btn-secondary" data-modal-close>Close</button>
        </div>
    </div>
</dialog>