<?php
require_once __DIR__ . '/../../includes/modal.php';
$extraScripts = ['/assets/js/pages/admin-users.js'];
?>
<div class="mb-6">
    <p class="text-md text-text-secondary">Search customers, review their status, and suspend or reactivate accounts.</p>
</div>

<div class="card mb-5">
    <form id="filters-form" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
        <div class="sm:col-span-2">
            <label for="f-search" class="field-label"><?= icon('search', 'w-3.5 h-3.5 inline -mt-0.5 mr-1') ?>Search name or email</label>
            <input type="search" id="f-search" name="search" class="field-input" placeholder="Priya, priya@verapay.test">
        </div>
        <div>
            <label for="f-status" class="field-label">Status</label>
            <select id="f-status" name="status" class="field-input">
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
                    <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody id="users-tbody">
                <tr><td colspan="5" class="text-center py-8 text-text-secondary">Loading customers…</td></tr>
            </tbody>
        </table>
    </div>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-4 border-t border-border" id="users-pagination"></div>
</div>

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
