(function () {
    'use strict';
    const { apiFetch, showToast, setButtonLoading, escapeHtml } = window.Verapay;

    const loadingEl = document.getElementById('identity-vault-loading');
    const contentEl = document.getElementById('identity-vault-content');

    const set = (id, value) => { document.getElementById(id).textContent = value || '—'; };

    async function load() {
        const { success, data, message } = await apiFetch('/api/profile/identity.php');
        loadingEl.classList.add('hidden');
        contentEl.classList.remove('hidden');

        if (!success) {
            showToast(message || 'Unable to load your details.', 'error');
            return;
        }

        // Settlement bank
                // Settlement bank
        if (data.bank) {
            document.getElementById('settlement-readonly').classList.remove('hidden');
            document.getElementById('settlement-empty').classList.add('hidden');
            set('sb-holder', data.bank.account_holder);
            set('sb-account', data.bank.account_number);
            set('sb-ifsc', data.bank.ifsc_code);
            set('sb-bank', data.bank.bank_name);
        } else {
            document.getElementById('settlement-empty').classList.remove('hidden');
        }

        // Business & KYC
        if (data.kyc_locked && data.profile) {
            document.getElementById('hero-verified-badge').classList.remove('hidden');
            document.getElementById('kyc-locked-banner').classList.remove('hidden');
            document.getElementById('kyc-form').classList.add('hidden');
            const ro = document.getElementById('kyc-readonly');
            ro.classList.remove('hidden');
            set('ro-company-name', data.profile.legal_company_name);
            set('ro-company-type', data.profile.company_type);
            set('ro-mobile', data.profile.mobile_number);
            set('ro-whatsapp', data.profile.whatsapp_number);
            set('ro-pan', data.profile.pan_number);
            set('ro-gstin', data.profile.gstin);
            set('ro-aadhar', data.profile.aadhar_number);
            set('ro-address', data.profile.office_address);
        }
    }

    document.getElementById('account-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const nameInput = document.getElementById('iv-name');
        const errorEl = document.getElementById('iv-name-error');
        errorEl.classList.add('hidden');

        if (!nameInput.value.trim()) {
            errorEl.textContent = 'Enter your name.';
            errorEl.classList.remove('hidden');
            nameInput.focus();
            return;
        }

        const btn = document.getElementById('account-submit');
        setButtonLoading(btn, true);
        const { success, message } = await apiFetch('/api/profile/update.php', { method: 'POST', body: { name: nameInput.value.trim() } });
        setButtonLoading(btn, false);

        if (!success) {
            errorEl.textContent = message || 'Unable to save changes.';
            errorEl.classList.remove('hidden');
            return;
        }
        showToast('Account details updated.', 'success');
    });

    const kycForm = document.getElementById('kyc-form');
    kycForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errorEl = document.getElementById('kyc-error');
        errorEl.classList.add('hidden');

        const fd = new FormData(kycForm);
        const body = Object.fromEntries(fd.entries());

        const btn = document.getElementById('kyc-submit');
        setButtonLoading(btn, true);
        const { success, message } = await apiFetch('/api/profile/update-identity.php', { method: 'POST', body });
        setButtonLoading(btn, false);

        if (!success) {
            errorEl.textContent = message || 'Unable to save details.';
            errorEl.classList.remove('hidden');
            return;
        }
        showToast('Business and KYC details saved.', 'success');
        load();
    });

    load();
})();
