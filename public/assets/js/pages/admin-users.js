(function () {
    'use strict';

    const {
        apiFetch,
        showToast,
        setButtonLoading,
        escapeHtml,
        openModal,
        closeModal
    } = window.Verapay;

    const form = document.getElementById('filters-form');
    const tbody = document.getElementById('users-tbody');
    const pagination = document.getElementById('users-pagination');

    const addCustomerForm = document.getElementById('add-customer-form');
    const addCustomerSubmit = document.getElementById('ac-submit');
    const addCustomerError = document.getElementById('ac-error');

    let searchDebounce;
    let pendingTarget = null;

    function avatarMarkup(u) {
        const initials = escapeHtml(u.avatar_initials || u.name.slice(0, 2));
        const gender = (u.gender === 'male' || u.gender === 'female') ? u.gender : (Number(u.id) % 2 === 0 ? 'female' : 'male');
        const folder = gender === 'male' ? 'men' : 'women';
        const idx = Number(u.id) % 100;
        return `<span class="avatar-chip-sm !bg-transparent relative">
            <img src="https://randomuser.me/api/portraits/${folder}/${idx}.jpg" alt="" class="absolute inset-0 w-full h-full rounded-full object-cover" loading="lazy" referrerpolicy="no-referrer"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <span class="hidden absolute inset-0 rounded-full items-center justify-center bg-brand-muted text-brand-emphasis">${initials}</span>
        </span>`;
    }

    function buildQuery(page) {
        const params = new URLSearchParams(new FormData(form));

        [...params.keys()].forEach((key) => {
            if (!params.get(key)) {
                params.delete(key);
            }
        });

        params.set('page', page);
        params.set('per_page', 15);

        return params.toString();
    }

    async function load(page = 1) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5"
                    class="text-center py-8 text-text-secondary">
                    Loading customers…
                </td>
            </tr>
        `;

        const {
            success,
            data,
            message
        } = await apiFetch(
            '/api/admin/users/list.php?' + buildQuery(page)
        );

        if (!success) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <span class="empty-state-icon"><svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><circle cx="12" cy="16" r="0.9" fill="currentColor" stroke="none"/></svg></span>
                            <p class="empty-state-title">We couldn't load customers.</p>
                            <p class="empty-state-body">${escapeHtml(message || 'Please try again.')}</p>
                            <button class="btn-secondary" id="users-retry">Try again</button>
                        </div>
                    </td>
                </tr>
            `;

            document
                .getElementById('users-retry')
                ?.addEventListener('click', () => load(page));

            pagination.innerHTML = '';

            return;
        }

        if (!data.users.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <span class="empty-state-icon"><svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.5-4.5"/></svg></span>
                            <p class="empty-state-title">No customers match these filters</p>
                            <p class="empty-state-body">Try adjusting your search or status filter.</p>
                        </div>
                    </td>
                </tr>
            `;

            pagination.innerHTML = '';

            return;
        }

        tbody.innerHTML = data.users.map((u) => `
            <tr class="border-l-4 ${u.status === 'active' ? 'border-l-success' : 'border-l-danger'}">
                <td>
                    <span class="flex items-center gap-2.5">
                        ${avatarMarkup(u)}

                        <span class="min-w-0">
                            <span class="block text-md font-medium text-text-primary truncate">
                                ${escapeHtml(u.name)}
                            </span>

                            <span class="block text-sm text-text-secondary truncate">
                                ${escapeHtml(u.email)}
                            </span>
                        </span>
                    </span>
                </td>

                <td class="capitalize">
                    ${escapeHtml(u.role)}
                </td>

                <td>
                    <span class="${u.status === 'active'
                        ? 'badge-success'
                        : 'badge-danger'}">
                        ${escapeHtml(u.status)}
                    </span>
                </td>

                <td class="text-text-secondary whitespace-nowrap">
                    ${new Date(u.created_at).toLocaleDateString()}
                </td>

                <td class="text-right">
                    <span class="inline-flex items-center gap-2">
                        ${u.role === 'customer'
                            ? `
                                <a href="/admin/kyc-review?user_id=${u.id}"
                                   class="btn-secondary !px-3 !py-2 inline-flex items-center gap-1.5"
                                   title="${u.kyc_uploaded_count}/${u.kyc_total_types} documents uploaded${u.kyc_pending_count ? `, ${u.kyc_pending_count} pending review` : ''}">
                                    KYC
                                    ${u.kyc_pending_count > 0
                                        ? `<span class="badge-warning !px-1.5 !py-0.5 !text-xs">${u.kyc_pending_count}</span>`
                                        : ''}
                                </a>
                            `
                            : ''}

                        ${u.role === 'customer'
                            ? `
                                <button type="button"
                                        class="btn-secondary !px-3 !py-2 api-access-btn"
                                        data-id="${u.id}"
                                        data-name="${escapeHtml(u.name)}">
                                    API Access
                                </button>
                            `
                            : ''}

                        ${u.status === 'active'
                            ? `
                                <button type="button"
                                        class="btn-danger !px-3 !py-2 suspend-btn"
                                        data-id="${u.id}"
                                        data-name="${escapeHtml(u.name)}">
                                    Suspend
                                </button>
                            `
                            : `
                                <button type="button"
                                        class="btn-secondary !px-3 !py-2 reactivate-btn"
                                        data-id="${u.id}"
                                        data-name="${escapeHtml(u.name)}">
                                    Reactivate
                                </button>
                            `}
                    </span>
                </td>
            </tr>
        `).join('');

        tbody
            .querySelectorAll('.suspend-btn')
            .forEach((btn) => {
                btn.addEventListener('click', () => {
                    pendingTarget = {
                        id: btn.dataset.id,
                        name: btn.dataset.name,
                        page
                    };

                    document
                        .getElementById('suspend-modal-body')
                        .textContent =
                        `${btn.dataset.name} will be signed out immediately and unable to access Verapay until reactivated.`;

                    openModal('suspend-modal');
                });
            });

        tbody
            .querySelectorAll('.reactivate-btn')
            .forEach((btn) => {
                btn.addEventListener('click', () => {
                    pendingTarget = {
                        id: btn.dataset.id,
                        name: btn.dataset.name,
                        page
                    };

                    document
                        .getElementById('reactivate-modal-body')
                        .textContent =
                        `${btn.dataset.name} will regain access to Verapay and can sign in again.`;

                    openModal('reactivate-modal');
                });
            });

        tbody
            .querySelectorAll('.api-access-btn')
            .forEach((btn) => {
                btn.addEventListener('click', () => openApiAccessModal(btn.dataset.id, btn.dataset.name));
            });

        const {
            page: currentPage,
            total_pages,
            total
        } = data.pagination;

        pagination.innerHTML = `
            <span class="text-sm text-text-secondary">
                Showing page ${currentPage} of ${total_pages} (${total} total)
            </span>

            <div class="flex items-center gap-2">
                <button type="button"
                        class="btn-secondary !px-4 !py-2"
                        id="users-prev"
                        ${currentPage <= 1 ? 'disabled' : ''}>
                    Previous
                </button>

                <button type="button"
                        class="btn-secondary !px-4 !py-2"
                        id="users-next"
                        ${currentPage >= total_pages ? 'disabled' : ''}>
                    Next
                </button>
            </div>
        `;

        document
            .getElementById('users-prev')
            ?.addEventListener('click', () => load(currentPage - 1));

        document
            .getElementById('users-next')
            ?.addEventListener('click', () => load(currentPage + 1));
    }

    addCustomerForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        addCustomerError.classList.add('hidden');
        addCustomerError.textContent = '';

        const name = document
            .getElementById('ac-name')
            .value
            .trim();

        const email = document
            .getElementById('ac-email')
            .value
            .trim();

        const password = document
            .getElementById('ac-password')
            .value;

        const gender = document
            .getElementById('ac-gender')
            .value;

        if (!name) {
            addCustomerError.textContent = 'Please enter the customer name.';
            addCustomerError.classList.remove('hidden');
            return;
        }

        if (!email) {
            addCustomerError.textContent = 'Please enter the customer email.';
            addCustomerError.classList.remove('hidden');
            return;
        }

        if (password.length < 8) {
            addCustomerError.textContent =
                'Password must contain at least 8 characters.';

            addCustomerError.classList.remove('hidden');

            return;
        }

        setButtonLoading(addCustomerSubmit, true);

        const {
            success,
            message
        } = await apiFetch(
            '/api/admin/users/create.php',
            {
                method: 'POST',
                body: {
                    name,
                    email,
                    password,
                    gender
                }
            }
        );

        setButtonLoading(addCustomerSubmit, false);

        if (!success) {
            addCustomerError.textContent =
                message || 'Unable to create the customer.';

            addCustomerError.classList.remove('hidden');

            return;
        }

        closeModal('add-customer-modal');

        addCustomerForm.reset();

        showToast(
            message || 'Customer created successfully.',
            'success'
        );

        load(1);
    });

    document
        .getElementById('suspend-confirm-btn')
        .addEventListener('click', async function () {
            if (!pendingTarget) {
                return;
            }

            setButtonLoading(this, true);

            const {
                success,
                message
            } = await apiFetch(
                '/api/admin/users/suspend.php',
                {
                    method: 'POST',
                    body: {
                        user_id: pendingTarget.id
                    }
                }
            );

            setButtonLoading(this, false);

            closeModal('suspend-modal');

            showToast(
                message,
                success ? 'success' : 'error'
            );

            if (success) {
                load(pendingTarget.page);
            }

            pendingTarget = null;
        });

    document
        .getElementById('reactivate-confirm-btn')
        .addEventListener('click', async function () {
            if (!pendingTarget) {
                return;
            }

            setButtonLoading(this, true);

            const {
                success,
                message
            } = await apiFetch(
                '/api/admin/users/reactivate.php',
                {
                    method: 'POST',
                    body: {
                        user_id: pendingTarget.id
                    }
                }
            );

            setButtonLoading(this, false);

            closeModal('reactivate-modal');

            showToast(
                message,
                success ? 'success' : 'error'
            );

            if (success) {
                load(pendingTarget.page);
            }

            pendingTarget = null;
        });

    // --- API Access modal ---------------------------------------------

    let apiAccessUserId = null;

    async function openApiAccessModal(userId, name) {
        apiAccessUserId = userId;

        document.getElementById('api-access-title').textContent = `API Access — ${name}`;
        document.getElementById('api-access-token-reveal').classList.add('hidden');
        document.getElementById('api-access-error').classList.add('hidden');

        openModal('api-access-modal');
        renderApiAccessLoading();

        const { success, data, message } = await apiFetch(`/api/admin/api-clients/get.php?user_id=${userId}`);

        if (!success) {
            document.getElementById('api-access-error').textContent = message;
            document.getElementById('api-access-error').classList.remove('hidden');
            return;
        }

        renderApiAccessState(data);
    }

    function renderApiAccessLoading() {
        document.getElementById('api-access-status').textContent = 'Loading…';
        document.getElementById('api-access-settings-form').classList.add('hidden');
    }

    function renderApiAccessState(data) {
        const statusEl = document.getElementById('api-access-status');
        const revokeBtn = document.getElementById('api-access-revoke-btn');
        const generateBtn = document.getElementById('api-access-generate-btn');
        const settingsFields = document.getElementById('api-access-settings-form');

        if (!data.credential) {
            statusEl.innerHTML = `<span class="badge-neutral">Not configured</span>`;
            revokeBtn.classList.add('hidden');
            generateBtn.textContent = 'Generate API key';
            settingsFields.classList.add('hidden');
            return;
        }

        const c = data.credential;
        const badge = c.status === 'active' ? 'badge-success' : 'badge-danger';

        statusEl.innerHTML = `
            <span class="${badge}">${escapeHtml(c.status)}</span>
            <span class="text-text-secondary text-sm ml-2">
                ${escapeHtml(c.client_key)} · ends in ${escapeHtml(c.token_last4)}
            </span>
        `;

        revokeBtn.classList.toggle('hidden', c.status !== 'active');
        generateBtn.textContent = 'Rotate API key';

        document.getElementById('api-access-payin').value = c.payin_callback_url || '';
        document.getElementById('api-access-payout').value = c.payout_callback_url || '';
        document.getElementById('api-access-ips').value = (data.whitelisted_ips || []).join('\n');
        settingsFields.classList.remove('hidden');
    }

    document
        .getElementById('api-access-generate-btn')
        .addEventListener('click', async function () {
            if (!apiAccessUserId) {
                return;
            }

            setButtonLoading(this, true);

            const { success, data, message } = await apiFetch(
                '/api/admin/api-clients/generate.php',
                { method: 'POST', body: { user_id: apiAccessUserId } }
            );

            setButtonLoading(this, false);

            if (!success) {
                showToast(message, 'error');
                return;
            }

            document.getElementById('api-access-token-value').textContent = data.token;
            document.getElementById('api-access-token-reveal').classList.remove('hidden');

            showToast(message, 'success');

            const refreshed = await apiFetch(`/api/admin/api-clients/get.php?user_id=${apiAccessUserId}`);
            if (refreshed.success) {
                renderApiAccessState(refreshed.data);
            }
        });

    document
        .getElementById('api-access-revoke-btn')
        .addEventListener('click', async function () {
            if (!apiAccessUserId || !confirm('Revoke this customer\'s API access? Their key will stop working immediately.')) {
                return;
            }

            setButtonLoading(this, true);

            const { success, message } = await apiFetch(
                '/api/admin/api-clients/revoke.php',
                { method: 'POST', body: { user_id: apiAccessUserId } }
            );

            setButtonLoading(this, false);
            showToast(message, success ? 'success' : 'error');

            if (success) {
                const refreshed = await apiFetch(`/api/admin/api-clients/get.php?user_id=${apiAccessUserId}`);
                if (refreshed.success) {
                    renderApiAccessState(refreshed.data);
                }
            }
        });

    document
        .getElementById('api-access-settings-form')
        .addEventListener('submit', async function (event) {
            event.preventDefault();

            if (!apiAccessUserId) {
                return;
            }

            const submitBtn = document.getElementById('api-access-settings-submit');
            setButtonLoading(submitBtn, true);

            const { success, message } = await apiFetch(
                '/api/admin/api-clients/save-settings.php',
                {
                    method: 'POST',
                    body: {
                        user_id: apiAccessUserId,
                        payin_callback_url: document.getElementById('api-access-payin').value.trim(),
                        payout_callback_url: document.getElementById('api-access-payout').value.trim(),
                        whitelisted_ips: document.getElementById('api-access-ips').value,
                    }
                }
            );

            setButtonLoading(submitBtn, false);
            showToast(message, success ? 'success' : 'error');
        });

    form.addEventListener('input', (event) => {
        if (event.target.id === 'f-search') {
            clearTimeout(searchDebounce);

            searchDebounce = setTimeout(() => {
                load(1);
            }, 350);
        }
    });

    form.addEventListener('change', (event) => {
        if (event.target.id !== 'f-search') {
            load(1);
        }
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        load(1);
    });

    load(1);
})();