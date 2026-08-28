(function () {
    'use strict';
    const { apiFetch, showToast, setButtonLoading } = window.Verapay;

    const badgeClass = { pending: 'badge-warning', verified: 'badge-success', rejected: 'badge-danger' };
    const badgeLabel = { pending: 'Pending review', verified: 'Verified', rejected: 'Rejected' };

    function renderDoc(type, doc) {
        const card = document.querySelector(`[data-doc-card="${type}"]`);
        if (!card) return;
        const badge = card.querySelector('[data-doc-badge]');
        const filenameWrap = card.querySelector('[data-doc-filename]');
        const filenameText = card.querySelector('[data-doc-filename-text]');
        const viewLink = card.querySelector('[data-doc-view]');
        const submitBtn = card.querySelector('[data-doc-submit] span');

        if (doc) {
            badge.textContent = badgeLabel[doc.status] || doc.status;
            badge.className = 'badge ' + (badgeClass[doc.status] || 'badge-neutral');
            badge.classList.remove('hidden');
            filenameText.textContent = doc.original_filename;
            viewLink.href = `/api/kyc/download.php?document_type=${encodeURIComponent(type)}`;
            filenameWrap.classList.remove('hidden');
            if (submitBtn) submitBtn.textContent = 'Replace';
        } else {
            badge.classList.add('hidden');
            filenameWrap.classList.add('hidden');
            if (submitBtn) submitBtn.textContent = 'Upload';
        }
    }

    function updateProgress() {
        const total = document.querySelectorAll('[data-doc-card]').length;
        const uploaded = document.querySelectorAll('[data-doc-filename]:not(.hidden)').length;
        const countEl = document.getElementById('kyc-progress-count');
        const fillEl = document.getElementById('kyc-progress-fill');
        if (countEl) countEl.textContent = uploaded;
        if (fillEl) fillEl.style.width = `${total ? (uploaded / total) * 100 : 0}%`;
    }

    async function load() {
        const { success, data, message } = await apiFetch('/api/kyc/documents.php');
        if (!success) {
            showToast(message || 'Unable to load your documents.', 'error');
            return;
        }
        Object.entries(data.documents).forEach(([type, doc]) => renderDoc(type, doc));
        updateProgress();
    }

    document.querySelectorAll('[data-doc-form]').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const type = form.dataset.docType;
            const input = form.querySelector('[data-doc-input]');
            const errorEl = form.querySelector('[data-doc-error]');
            const btn = form.querySelector('[data-doc-submit]');
            errorEl.classList.add('hidden');

            if (!input.files || !input.files[0]) {
                errorEl.textContent = 'Choose a file first.';
                errorEl.classList.remove('hidden');
                return;
            }

            const fd = new FormData();
            fd.append('document_type', type);
            fd.append('document', input.files[0]);

            setButtonLoading(btn, true);
            const { success, message } = await apiFetch('/api/kyc/upload.php', { method: 'POST', body: fd });
            setButtonLoading(btn, false);

            if (!success) {
                errorEl.textContent = message || 'Upload failed.';
                errorEl.classList.remove('hidden');
                return;
            }

            input.value = '';
            showToast('Document uploaded.', 'success');
            load();
        });
    });

    load();
})();
