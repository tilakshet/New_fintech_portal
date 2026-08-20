(function () {
    'use strict';
    const { apiFetch, showToast, setButtonLoading, escapeHtml, openModal, closeModal } = window.Verapay;

    const POLL_INTERVAL_MS = 4000;
    let activeConversationId = null;
    let lastMessageId = 0;
    let pollTimer = null;

    function timeLabel(iso) {
        return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    function messageBubble(m) {
        const isMine = m.sender_role === 'customer';
        return `
            <div class="flex ${isMine ? 'justify-end' : 'justify-start'}">
                <div class="max-w-[80%] rounded-md px-4 py-2.5 ${isMine ? 'bg-brand text-white' : 'bg-surface-muted text-text-primary'}">
                    ${!isMine ? `<span class="block text-xs font-semibold ${isMine ? 'text-white/80' : 'text-text-secondary'} mb-0.5">${escapeHtml(m.sender_name)} · Support</span>` : ''}
                    <span class="block text-md whitespace-pre-wrap break-words">${escapeHtml(m.message)}</span>
                    <span class="block text-xs mt-1 ${isMine ? 'text-white/70' : 'text-text-secondary'}">${timeLabel(m.created_at)}</span>
                </div>
            </div>`;
    }

    async function loadConversations(selectId = null) {
        const list = document.getElementById('conversation-list');
        const { success, data } = await apiFetch('/api/support/conversations.php');
        if (!success || !data.conversations.length) {
            list.innerHTML = '<li class="px-5 py-6 text-sm text-text-secondary text-center">No conversations yet.</li>';
            return;
        }
        list.innerHTML = data.conversations.map((c) => `
            <li>
                <button type="button" data-conversation-id="${c.id}" class="conversation-item w-full text-left px-5 py-4 hover:bg-surface-muted ${c.id === activeConversationId ? 'bg-brand-muted/40' : ''}">
                    <span class="flex items-center justify-between gap-2">
                        <span class="text-md font-semibold text-text-primary truncate">${escapeHtml(c.subject)}</span>
                        ${c.unread_count > 0 ? `<span class="w-2 h-2 rounded-full bg-danger shrink-0" aria-label="${c.unread_count} unread"></span>` : ''}
                    </span>
                    <span class="block text-sm text-text-secondary truncate mt-0.5">${escapeHtml(c.last_message || '')}</span>
                </button>
            </li>`).join('');

        list.querySelectorAll('.conversation-item').forEach((btn) => {
            btn.addEventListener('click', () => selectConversation(parseInt(btn.dataset.conversationId, 10), c => c.subject, data.conversations));
        });

        const toSelect = selectId || activeConversationId || data.conversations[0].id;
        const conv = data.conversations.find((c) => c.id === toSelect) || data.conversations[0];
        if (conv) selectConversation(conv.id, null, data.conversations, conv);
    }

    function selectConversation(id, _unused, conversations, convObj) {
        if (activeConversationId === id && document.getElementById('thread-active').classList.contains('hidden') === false) {
            // still refresh messages
        }
        activeConversationId = id;
        lastMessageId = 0;
        document.getElementById('thread-empty').classList.add('hidden');
        document.getElementById('thread-active').classList.remove('hidden');
        document.getElementById('thread-messages').innerHTML = '<p class="text-sm text-text-secondary text-center py-6">Loading messages…</p>';

        const conv = convObj || (conversations || []).find((c) => c.id === id);
        if (conv) {
            document.getElementById('thread-subject').textContent = conv.subject;
            const statusEl = document.getElementById('thread-status');
            statusEl.textContent = conv.status;
            statusEl.className = conv.status === 'open' ? 'badge-success' : 'badge-neutral';
        }

        document.querySelectorAll('.conversation-item').forEach((btn) => {
            btn.classList.toggle('bg-brand-muted/40', parseInt(btn.dataset.conversationId, 10) === id);
        });

        loadMessages(true);
        restartPolling();
    }

    async function loadMessages(replace = false) {
        if (!activeConversationId) return;
        const { success, data } = await apiFetch(`/api/support/messages.php?conversation_id=${activeConversationId}&after_id=${replace ? 0 : lastMessageId}`);
        if (!success) return;

        const container = document.getElementById('thread-messages');
        if (replace) container.innerHTML = '';
        if (!data.messages.length && replace) {
            container.innerHTML = '<p class="text-sm text-text-secondary text-center py-6">No messages yet.</p>';
            return;
        }
        if (data.messages.length) {
            container.innerHTML += data.messages.map(messageBubble).join('');
            lastMessageId = data.messages[data.messages.length - 1].id;
            container.scrollTop = container.scrollHeight;
        }
    }

    function restartPolling() {
        clearInterval(pollTimer);
        pollTimer = setInterval(() => {
            if (document.visibilityState === 'visible') loadMessages(false);
        }, POLL_INTERVAL_MS);
    }
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') loadMessages(false);
    });

    document.getElementById('reply-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const textarea = document.getElementById('reply-message');
        const message = textarea.value.trim();
        if (!message || !activeConversationId) return;

        const submitBtn = document.getElementById('reply-submit');
        setButtonLoading(submitBtn, true);
        const { success, message: msg } = await apiFetch('/api/support/messages.php', {
            method: 'POST',
            body: { conversation_id: activeConversationId, message },
        });
        setButtonLoading(submitBtn, false);

        if (!success) {
            showToast(msg || 'Unable to send message.', 'error');
            return;
        }
        textarea.value = '';
        loadMessages(false);
        loadConversations(activeConversationId);
    });

    document.getElementById('new-conversation-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const subject = document.getElementById('nc-subject').value.trim();
        const message = document.getElementById('nc-message').value.trim();
        const errorEl = document.getElementById('nc-error');
        errorEl.classList.add('hidden');

        const submitBtn = document.getElementById('nc-submit');
        setButtonLoading(submitBtn, true);
        const { success, data, message: msg } = await apiFetch('/api/support/conversations.php', {
            method: 'POST',
            body: { subject, message },
        });
        setButtonLoading(submitBtn, false);

        if (!success) {
            errorEl.textContent = msg || 'Unable to start this conversation.';
            errorEl.classList.remove('hidden');
            return;
        }
        closeModal('new-conversation-modal');
        e.target.reset();
        await loadConversations(data.conversation_id);
    });

    loadConversations();
})();
