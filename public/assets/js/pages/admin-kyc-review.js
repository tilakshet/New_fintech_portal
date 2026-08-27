(function () {
    'use strict';
    const { apiFetch, escapeHtml, showToast, setButtonLoading } = window.Verapay;

    const errorEl = document.getElementById('kyc-review-error');
    const contentEl = document.getElementById('kyc-review-content');
    const loadingEl = document.getElementById('kyc-review-loading');
    const avatarEl = document.getElementById('kyc-review-avatar');
    const nameEl = document.getElementById('kyc-review-name');
    const emailEl = document.getElementById('kyc-review-email');
    const badgesEl = document.getElementById('kyc-review-summary-badges');
    const gridEl = document.getElementById('kyc-review-grid');

    const badgeClass = { pending: 'badge-warning', verified: 'badge-success', rejected: 'badge-danger' };
    const badgeLabel = { pending: 'Pending review', verified: 'Verified', rejected: 'Rejected' };

    const userId = new URLSearchParams(window.location.search).get('user_id');

    function showError() {
        loadingEl.classList.add('hidden');
        contentEl.classList.add('hidden');
        errorEl.classList.remove('hidden');
    }

    function renderCard(doc) {
        const uploaded = doc.uploaded;
        const status = doc.status;

        const badgeHtml = uploaded
            ? `<span class="${badgeClass[status] || 'badge-neutral'}">${escapeHtml(badgeLabel[status] || status)}</span>`
            : `<span class="badge-neutral">Not uploaded</span>`;

        const fileInfoHtml = uploaded
            ? `<p class="text-sm text-text-secondary mb-3 truncate">
                    ${escapeHtml(doc.original_filename)}
                    &middot; <a href="/api/admin/kyc/download.php?user_id=${encodeURIComponent(userId)}&document_type=${encodeURIComponent(doc.type)}" target="_blank" rel="noopener" class="text-brand-emphasis hover:underline">View</a>
                </p>`
            : `<p class="text-sm text-text-secondary mb-3">No document uploaded yet.</p>`;

        const actionsHtml = uploaded
            ? `<div class="flex gap-2">
                    <button type="button" class="btn-secondary flex-1 kyc-approve-btn" data-type="${escapeHtml(doc.type)}" ${status === 'verified' ? 'disabled' : ''}>
                        ${icon_check()} Approve
                    </button>
                    <button type="button" class="btn-danger flex-1 kyc-reject-btn" data-type="${escapeHtml(doc.type)}" ${status === 'rejected' ? 'disabled' : ''}>
                        ${icon_close()} Reject
                    </button>
                </div>`
            : '';

        return `
            <div class="card" data-doc-card="${escapeHtml(doc.type)}">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <h2 class="card-title">${escapeHtml(doc.label)}</h2>
                    ${badgeHtml}
                </div>
                ${fileInfoHtml}
                ${actionsHtml}
            </div>`;
    }

    // Small inline icons so this file doesn't depend on the PHP icon() helper.
    function icon_check() {
        return '<svg class="w-4 h-4 inline -mt-0.5 mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg>';
    }
    function icon_close() {
        return '<svg class="w-4 h-4 inline -mt-0.5 mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>';
    }

    function wireCardActions() {
        gridEl.querySelectorAll('.kyc-approve-btn').forEach((btn) => {
            btn.addEventListener('click', () => decide(btn, btn.dataset.type, 'verified'));
        });
        gridEl.querySelectorAll('.kyc-reject-btn').forEach((btn) => {
            btn.addEventListener('click', () => decide(btn, btn.dataset.type, 'rejected'));
        });
    }

    async function decide(btn, type, decision) {
        setButtonLoading(btn, true);
        const { success, message } = await apiFetch('/api/admin/kyc/review.php', {
            method: 'POST',
            body: { user_id: userId, document_type: type, decision },
        });
        setButtonLoading(btn, false);

        showToast(message, success ? 'success' : 'error');
        if (success) load();
    }

    async function load() {
        if (!userId) {
            showError();
            return;
        }

        const { success, data } = await apiFetch('/api/admin/kyc/documents.php?user_id=' + encodeURIComponent(userId));

        if (!success) {
            showError();
            return;
        }

        const { customer, documents } = data;

        avatarEl.textContent = customer.avatar_initials || customer.name.slice(0, 2).toUpperCase();
        nameEl.textContent = customer.name;
        emailEl.textContent = customer.email;

        const uploadedCount = documents.filter((d) => d.uploaded).length;
        const verifiedCount = documents.filter((d) => d.status === 'verified').length;
        const pendingCount = documents.filter((d) => d.status === 'pending').length;

        badgesEl.innerHTML = `
            <span class="badge-neutral">${uploadedCount}/${documents.length} uploaded</span>
            ${pendingCount ? `<span class="badge-warning">${pendingCount} pending</span>` : ''}
            ${verifiedCount ? `<span class="badge-success">${verifiedCount} verified</span>` : ''}
        `;

        gridEl.innerHTML = documents.map(renderCard).join('');
        wireCardActions();

        loadingEl.classList.add('hidden');
        errorEl.classList.add('hidden');
        contentEl.classList.remove('hidden');
    }

    load();
})();
