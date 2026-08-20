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

    // ---------------- Business & KYC / settlement bank (customer only) ----------------
    const businessForm = document.getElementById('business-form');
    const bankForm = document.getElementById('bank-form');
    if (!businessForm && !bankForm) return;

    async function loadBusinessProfile() {
        const { success, data } = await apiFetch('/api/profile/business.php');
        if (!success || !data.profile) return;
        const p = data.profile;

        const setValue = (id, value) => { const el = document.getElementById(id); if (el) el.value = value || ''; };
        setValue('b-company-name', p.legal_company_name);
        setValue('b-company-type', p.company_type);
        setValue('b-pan', p.pan_number);
        setValue('b-mobile', p.mobile_number);
        setValue('b-whatsapp', p.whatsapp_number);
        setValue('b-gstin', p.gstin);
        setValue('b-address', p.office_address);
        setValue('bank-holder', p.bank_account_holder);
        setValue('bank-ifsc', p.bank_ifsc);

        if (p.identity_last4) {
            const identityInput = document.getElementById('b-identity');
            identityInput.placeholder = `On file: •••• •••• ${p.identity_last4}`;
            document.getElementById('b-identity-help').textContent =
                `On file ending ${p.identity_last4}. Leave blank to keep it, or enter a new number to replace it.`;
        }
        if (p.bank_account_last4) {
            const bankInput = document.getElementById('bank-account');
            bankInput.placeholder = `On file: •••• ${p.bank_account_last4}`;
            document.getElementById('bank-account-help').textContent =
                `On file ending ${p.bank_account_last4}. Leave blank to keep it, or enter a new number to replace it.`;
        }
    }

    businessForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errorEl = document.getElementById('business-error');
        errorEl.classList.add('hidden');

        const submitBtn = document.getElementById('business-submit');
        setButtonLoading(submitBtn, true);
        const { success, message } = await apiFetch('/api/profile/business.php', {
            method: 'POST',
            body: {
                legal_company_name: document.getElementById('b-company-name').value.trim(),
                company_type: document.getElementById('b-company-type').value,
                pan_number: document.getElementById('b-pan').value.trim(),
                mobile_number: document.getElementById('b-mobile').value.trim(),
                whatsapp_number: document.getElementById('b-whatsapp').value.trim(),
                gstin: document.getElementById('b-gstin').value.trim(),
                office_address: document.getElementById('b-address').value.trim(),
                identity_number: document.getElementById('b-identity').value.trim(),
            },
        });
        setButtonLoading(submitBtn, false);

        if (!success) {
            errorEl.textContent = message || 'Unable to save business information.';
            errorEl.classList.remove('hidden');
            return;
        }
        document.getElementById('b-identity').value = '';
        showToast('Business information saved.', 'success');
        loadBusinessProfile();
    });

    bankForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errorEl = document.getElementById('bank-error');
        errorEl.classList.add('hidden');

        const submitBtn = document.getElementById('bank-submit');
        setButtonLoading(submitBtn, true);
        const { success, message } = await apiFetch('/api/profile/business.php', {
            method: 'POST',
            body: {
                bank_account_holder: document.getElementById('bank-holder').value.trim(),
                bank_account_number: document.getElementById('bank-account').value.trim(),
                bank_ifsc: document.getElementById('bank-ifsc').value.trim(),
            },
        });
        setButtonLoading(submitBtn, false);

        if (!success) {
            errorEl.textContent = message || 'Unable to save settlement bank details.';
            errorEl.classList.remove('hidden');
            return;
        }
        document.getElementById('bank-account').value = '';
        showToast('Settlement bank saved.', 'success');
        loadBusinessProfile();
    });

    loadBusinessProfile();
})();
