/**
 * AI Concierge chat panel. Vanilla JS, no framework: sends guest messages to
 * the Laravel backend and renders both plain text and the structured
 * ui_payload the AI's tools attach (room cards, price quotes, booking
 * confirmations, handover notices) as real DOM, not markdown-in-a-bubble.
 */
function initConcierge() {
    const root = document.getElementById('concierge-app');
    const widget = document.querySelector('[data-chat-widget]');
    if (!root || !widget) return;

    const config = {
        startUrl: root.dataset.startUrl,
        messageUrl: root.dataset.messageUrl,
        historyUrl: root.dataset.historyUrl,
        storageKey: root.dataset.storageKey,
        locale: root.dataset.locale,
        currency: root.dataset.currency,
        labels: {
            placeholder: root.dataset.labelPlaceholder,
            send: root.dataset.labelSend,
            intro: root.dataset.labelIntro,
            thinking: root.dataset.labelThinking,
            handedOver: root.dataset.labelHandedOver,
            viewDetails: root.dataset.labelViewDetails,
            bookNow: root.dataset.labelBookNow,
            menuHeading: root.dataset.labelMenuHeading,
        },
    };

    const messagesEl = root.querySelector('[data-messages]');
    const formEl = root.querySelector('[data-chat-form]');
    const inputEl = root.querySelector('[data-chat-input]');
    const submitEl = root.querySelector('[data-chat-submit]');
    const statusBanner = root.querySelector('[data-status-banner]');
    const toggleButton = widget.querySelector('[data-chat-toggle]');
    const closeButton = widget.querySelector('[data-chat-close]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    let handedOver = false;
    let knownMessageCount = 0;
    let pollTimer = null;
    let isOpen = false;

    function openPanel() {
        if (isOpen) return;
        isOpen = true;
        root.classList.remove('hidden');
        root.classList.add('flex');
        scrollToBottom();
        inputEl.focus();
    }

    function closePanel() {
        isOpen = false;
        root.classList.add('hidden');
        root.classList.remove('flex');
    }

    toggleButton.addEventListener('click', () => (isOpen ? closePanel() : openPanel()));
    closeButton?.addEventListener('click', closePanel);

    function money(value) {
        const n = Number(value) || 0;
        return `${config.currency} ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(n)}`;
    }

    async function api(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: JSON.stringify(body || {}),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message || `Request failed (${response.status})`);
        }

        return response.json();
    }

    // Flat-illustration bot mascot (matches <x-concierge-avatar> in the
    // header) — never a photo, so the AI always reads as AI.
    function avatarMark() {
        const el = document.createElement('span');
        el.className =
            'flex h-7 w-7 shrink-0 items-center justify-center self-end rounded-full bg-gradient-to-br from-amber-600 to-amber-400 shadow-sm';
        el.innerHTML = `
            <svg viewBox="0 0 24 24" class="h-2/3 w-2/3">
                <rect x="9" y="2" width="6" height="3" rx="1.5" fill="#DC2626" />
                <rect x="4" y="5" width="16" height="14" rx="6" fill="#FFFFFF" />
                <rect x="8" y="11" width="2.4" height="3.2" rx="1.2" fill="#1E293B" />
                <rect x="13.6" y="11" width="2.4" height="3.2" rx="1.2" fill="#1E293B" />
                <rect x="9.4" y="16.2" width="5.2" height="1.6" rx="0.8" fill="#1E293B" />
            </svg>
        `;
        return el;
    }

    function bubble(role, text) {
        const wrap = document.createElement('div');
        wrap.className =
            role === 'guest' ? 'flex justify-end' : 'flex items-end justify-start gap-2';

        if (role !== 'guest') {
            wrap.appendChild(avatarMark());
        }

        const inner = document.createElement('div');
        inner.className =
            role === 'guest'
                ? 'max-w-[85%] rounded-2xl rounded-br-sm bg-stone-900 px-3 py-2 text-sm text-white'
                : 'max-w-[85%] rounded-2xl rounded-bl-sm bg-stone-100 px-3 py-2 text-sm text-stone-900';
        inner.textContent = text;

        wrap.appendChild(inner);
        return wrap;
    }

    function card(children) {
        const wrap = document.createElement('div');
        wrap.className = 'flex justify-start';
        const inner = document.createElement('div');
        inner.className = 'w-full max-w-[92%] space-y-2';
        children.forEach((c) => inner.appendChild(c));
        wrap.appendChild(inner);
        return wrap;
    }

    function roomResultCard(room) {
        const el = document.createElement('div');
        el.className = 'overflow-hidden rounded-xl border border-stone-200 bg-white';
        el.innerHTML = `
            ${room.thumbnail_url ? `<img src="${room.thumbnail_url}" alt="${room.name}" class="h-32 w-full object-cover">` : ''}
            <div class="p-3">
                <p class="font-medium text-stone-900">${room.name}</p>
                <p class="mt-0.5 text-xs text-stone-500">${room.size_sqm ?? '-'} m² · ${room.max_adults + room.max_children} guests${room.breakfast_included ? ' · breakfast included' : ''}</p>
                <p class="mt-1 text-sm font-semibold text-stone-900">${money(room.total_price)} <span class="font-normal text-stone-500">/ ${room.nights} night(s)</span></p>
                <button type="button" class="mt-2 w-full rounded-lg border border-stone-300 px-2 py-1.5 text-xs font-medium text-stone-700 hover:bg-stone-50" data-detail-slug="${room.room_type_slug}">
                    ${config.labels.viewDetails}
                </button>
            </div>
        `;
        el.querySelector('[data-detail-slug]').addEventListener('click', () => {
            sendMessage(`Show me more details and photos of ${room.name}`);
        });
        return el;
    }

    function roomDetailCard(room) {
        const el = document.createElement('div');
        el.className = 'overflow-hidden rounded-xl border border-stone-200 bg-white';

        const images = (room.images || [])
            .slice(0, 4)
            .map((img) => `<img src="${img.url}" alt="${img.alt || room.name}" class="h-20 w-full rounded-lg object-cover">`)
            .join('');

        el.innerHTML = `
            <div class="p-3">
                <p class="font-medium text-stone-900">${room.name}</p>
                <p class="mt-1 text-xs text-stone-500">${room.description || ''}</p>
                <div class="mt-2 grid grid-cols-4 gap-1">${images}</div>
                <dl class="mt-2 grid grid-cols-2 gap-1 text-xs text-stone-500">
                    <div>${room.size_sqm ?? '-'} m²</div>
                    <div>${room.max_adults + room.max_children} guests</div>
                    <div>${room.breakfast_included ? 'Breakfast included' : 'Room only'}</div>
                    <div>${room.view_type ? room.view_type + ' view' : ''}</div>
                </dl>
            </div>
        `;
        return el;
    }

    function availabilityCard(payload) {
        const el = document.createElement('div');
        el.className = 'rounded-xl border border-stone-200 bg-white p-3';

        if (!payload.available) {
            el.innerHTML = `<p class="text-sm text-stone-600">No availability for those dates.</p>`;
            return el;
        }

        const q = payload.quote;
        el.innerHTML = `
            <p class="font-medium text-stone-900">${q.name}</p>
            <p class="text-xs text-stone-500">${q.check_in} → ${q.check_out} · ${q.nights} night(s)</p>
            <p class="mt-1 text-base font-semibold text-stone-900">${money(q.grand_total)}</p>
        `;
        return el;
    }

    function bookingConfirmationCard(payload) {
        const b = payload.booking;
        const el = document.createElement('div');
        el.className = 'rounded-xl border border-emerald-200 bg-emerald-50 p-3';
        el.innerHTML = `
            <p class="text-sm font-semibold text-emerald-900">Booking request received</p>
            <p class="mt-1 text-xs text-emerald-800">Ref: ${b.booking_reference} · ${b.name}</p>
            <p class="text-xs text-emerald-800">${b.check_in} → ${b.check_out} · ${b.nights} night(s)</p>
            <p class="mt-1 text-sm font-semibold text-emerald-900">${money(b.total_price)}</p>
        `;
        return el;
    }

    function handoverCard(payload) {
        const el = document.createElement('div');
        el.className = 'rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800';
        el.textContent = config.labels.handedOver;
        return el;
    }

    function renderUiPayload(uiPayload) {
        if (!uiPayload || !uiPayload.length) return;

        uiPayload.forEach((payload) => {
            if (payload.type === 'room_results') {
                messagesEl.appendChild(card(payload.rooms.map(roomResultCard)));
            } else if (payload.type === 'room_detail') {
                messagesEl.appendChild(card([roomDetailCard(payload.room)]));
            } else if (payload.type === 'availability') {
                messagesEl.appendChild(card([availabilityCard(payload)]));
            } else if (payload.type === 'booking_confirmation') {
                messagesEl.appendChild(card([bookingConfirmationCard(payload)]));
            } else if (payload.type === 'handover') {
                messagesEl.appendChild(card([handoverCard(payload)]));
            }
        });
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function setBusy(busy) {
        inputEl.disabled = busy;
        submitEl.disabled = busy;
        submitEl.textContent = busy ? config.labels.thinking : config.labels.send;
    }

    function showHandedOverBanner() {
        handedOver = true;
        statusBanner.textContent = config.labels.handedOver;
        statusBanner.classList.remove('hidden');
        inputEl.disabled = true;
        submitEl.disabled = true;
        startPolling();
    }

    function clearHandedOverBanner() {
        handedOver = false;
        statusBanner.classList.add('hidden');
        inputEl.disabled = false;
        submitEl.disabled = false;
        stopPolling();
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(pollForStaffReplies, 4000);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    async function pollForStaffReplies() {
        const guestToken = localStorage.getItem(config.storageKey);
        if (!guestToken) return;

        try {
            const response = await fetch(`${config.historyUrl}?guest_token=${encodeURIComponent(guestToken)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;

            const data = await response.json();

            if (data.messages.length > knownMessageCount) {
                data.messages.slice(knownMessageCount).forEach(renderMessage);
                knownMessageCount = data.messages.length;
                scrollToBottom();
            }

            if (data.status !== 'handed_over') {
                clearHandedOverBanner();
            }
        } catch {
            // transient network hiccup — try again on the next tick
        }
    }

    function renderMessage(message) {
        if (message.role === 'guest') {
            messagesEl.appendChild(bubble('guest', message.content));
        } else if (message.role === 'assistant') {
            if (message.content) {
                messagesEl.appendChild(bubble('assistant', message.content));
            }
            renderUiPayload(message.ui_payload);
        } else if (message.role === 'staff') {
            messagesEl.appendChild(card([staffBubble(message.content)]));
        } else if (message.role === 'system') {
            messagesEl.appendChild(bubble('assistant', message.content));
        }
    }

    function quickMenuCard() {
        const template = document.querySelector('[data-quick-menu-items]');
        if (!template) return null;

        const el = document.createElement('div');
        el.className = 'overflow-hidden rounded-xl border border-stone-200 bg-white';

        const heading = document.createElement('p');
        heading.className = 'border-b border-stone-100 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-stone-400';
        heading.textContent = config.labels.menuHeading;
        el.appendChild(heading);

        const list = document.createElement('div');
        list.className = 'divide-y divide-stone-100';

        template.content.querySelectorAll('[data-quick-message]').forEach((source) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'flex w-full items-center justify-between px-3 py-2.5 text-left text-sm text-stone-700 hover:bg-amber-50';
            item.innerHTML = `<span>${source.textContent}</span><span class="text-stone-300">›</span>`;
            item.addEventListener('click', () => sendMessage(source.dataset.quickMessage));
            list.appendChild(item);
        });

        el.appendChild(list);
        return el;
    }

    function staffBubble(text) {
        const el = document.createElement('div');
        el.className = 'rounded-2xl rounded-bl-sm border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900';
        el.innerHTML = `<p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-500">Staff</p>${text}`;
        return el;
    }

    async function sendMessage(text) {
        if (!text.trim() || handedOver) return;

        const guestToken = localStorage.getItem(config.storageKey);
        if (!guestToken) return;

        messagesEl.appendChild(bubble('guest', text));
        scrollToBottom();
        setBusy(true);

        try {
            const data = await api(config.messageUrl, { guest_token: guestToken, message: text });
            renderMessage(data.message);
            knownMessageCount += 2; // the guest message just sent + the reply just rendered
            if (data.status === 'handed_over') {
                showHandedOverBanner();
            }
        } catch (err) {
            messagesEl.appendChild(bubble('assistant', err.message || 'Something went wrong. Please try again.'));
        } finally {
            setBusy(false);
            scrollToBottom();
        }
    }

    async function boot() {
        let guestToken = localStorage.getItem(config.storageKey);

        if (!guestToken) {
            const data = await api(config.startUrl, { locale: config.locale });
            guestToken = data.guest_token;
            localStorage.setItem(config.storageKey, guestToken);
            messagesEl.appendChild(bubble('assistant', config.labels.intro));
            const menu = quickMenuCard();
            if (menu) messagesEl.appendChild(card([menu]));
            return;
        }

        try {
            const response = await fetch(`${config.historyUrl}?guest_token=${encodeURIComponent(guestToken)}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) throw new Error('history_unavailable');

            const data = await response.json();

            if (!data.messages.length) {
                messagesEl.appendChild(bubble('assistant', config.labels.intro));
                const menu = quickMenuCard();
                if (menu) messagesEl.appendChild(card([menu]));
            } else {
                data.messages.forEach(renderMessage);
            }
            knownMessageCount = data.messages.length;

            if (data.status === 'handed_over') {
                showHandedOverBanner();
            }
        } catch {
            localStorage.removeItem(config.storageKey);
            return boot();
        }

        scrollToBottom();
    }

    formEl.addEventListener('submit', (event) => {
        event.preventDefault();
        const text = inputEl.value;
        inputEl.value = '';
        sendMessage(text);
    });

    document.querySelectorAll('[data-ask-ai-button]').forEach((button) => {
        button.addEventListener('click', () => {
            const roomCard = button.closest('[data-room-card]');
            const roomName = roomCard?.dataset.roomName || '';
            openPanel();
            sendMessage(`Tell me more about the ${roomName}`);
        });
    });

    // Hero quick-start menu — same topics as the in-chat menu, always
    // visible up front so guests see what the AI can do immediately.
    document.querySelectorAll('[data-hero-quick-message]').forEach((button) => {
        button.addEventListener('click', () => {
            openPanel();
            sendMessage(button.dataset.heroQuickMessage);
        });
    });

    boot();
}

document.addEventListener('DOMContentLoaded', initConcierge);
