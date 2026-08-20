(function () {
    'use strict';
    const { showToast } = window.Verapay;

    document.querySelectorAll('.code-copy-btn').forEach((btn) => {
        const defaultIcon = btn.querySelector('.copy-icon-default');
        const copiedIcon = btn.querySelector('.copy-icon-copied');
        let resetTimer;

        btn.addEventListener('click', async () => {
            const text = btn.dataset.copyText || '';
            try {
                await navigator.clipboard.writeText(text);
            } catch (err) {
                showToast('Unable to copy — your browser blocked clipboard access.', 'error');
                return;
            }

            defaultIcon?.classList.add('hidden');
            copiedIcon?.classList.remove('hidden');
            btn.setAttribute('aria-label', 'Copied to clipboard');

            clearTimeout(resetTimer);
            resetTimer = setTimeout(() => {
                defaultIcon?.classList.remove('hidden');
                copiedIcon?.classList.add('hidden');
                btn.setAttribute('aria-label', 'Copy code to clipboard');
            }, 1800);
        });
    });
})();
