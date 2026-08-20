(function () {
    'use strict';
    const { apiFetch, showToast, setButtonLoading } = window.Verapay;

    document.getElementById('profile-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const nameInput = document.getElementById('p-name');
        const errorEl = document.getElementById('p-name-error');
        errorEl.classList.add('hidden');
        nameInput.setAttribute('aria-invalid', 'false');

        if (!nameInput.value.trim()) {
            errorEl.textContent = 'Enter your name.';
            errorEl.classList.remove('hidden');
            nameInput.setAttribute('aria-invalid', 'true');
            nameInput.focus();
            return;
        }

        const submitBtn = document.getElementById('profile-submit');
        setButtonLoading(submitBtn, true);
        const { success, message } = await apiFetch('/api/profile/update.php', { method: 'POST', body: { name: nameInput.value.trim() } });
        setButtonLoading(submitBtn, false);

        if (!success) {
            errorEl.textContent = message || 'Unable to save changes.';
            errorEl.classList.remove('hidden');
            return;
        }
        showToast('Profile updated.', 'success');
    });
})();
