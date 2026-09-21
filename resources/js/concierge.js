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
            staff: root.dataset.labelStaff,
            error: root.dataset.labelError,
            slow: root.dataset.labelSlow,
            retry: root.dataset.labelRetry,
        },
    };

    const messagesEl = root.querySelector('[data-messages]');
    const formEl = root.querySelector('[data-chat-form]');
    const inputEl = root.querySelector('[data-chat-input]');
    const submitEl = root.querySelector('[data-chat-submit]');
    const statusBanner = root.querySelector('[data-status-banner]');
    const closeButton = widget.querySelector('[data-chat-close]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    let ready = false;
    let busy = false;
    let handedOver = false;
    let knownMessageCount = 0;
    let pollTimer = null;
    let isOpen = false;

    function openPanel() {
        isOpen = true;
        root.classList.add('is-open');
        scrollToBottom();
    }

    function closePanel() {
        isOpen = false;
        root.classList.remove('is-open');
    }

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
            const error = new Error(payload.message || `Request failed (${response.status})`);
            error.status = response.status;
            throw error;
        }

        return response.json();
    }

    function avatarMark() {
        const el = document.createElement('span');
        el.className = 'chat-host-avatar';
        el.setAttribute('aria-hidden', 'true');
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

    function setBusy(value) {
        busy = value;
        const disabled = busy || !ready || handedOver;
        inputEl.disabled = disabled;
        submitEl.disabled = disabled;
        root.setAttribute('aria-busy', String(busy));
        document.body.classList.toggle('is-thinking', busy);
        document.querySelectorAll('[data-hero-quick-message], [data-ask-ai-button], [data-quick-message]')
            .forEach((button) => { button.disabled = disabled; });
        submitEl.textContent = busy ? config.labels.thinking : config.labels.send;
    }

    function showHandedOverBanner() {
        handedOver = true;
        statusBanner.textContent = config.labels.handedOver;
        statusBanner.classList.remove('hidden');
        setBusy(busy);
        startPolling();
    }

    function clearHandedOverBanner() {
        handedOver = false;
        statusBanner.classList.add('hidden');
        setBusy(busy);
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
                window.hotelSound?.play('incoming');
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
            const chips = actionChips(message.suggested_actions);
            if (chips) messagesEl.appendChild(chips);
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

    const sceneNames = { home: 'reception', info: 'reception', rooms: 'rooms', room: 'room_detail', facilities: 'facilities', facility: 'facility_detail', reservation: 'reservation', staff: 'handover' };

    /**
     * Where the guest is in the lobby, so the concierge can answer for "this
     * room" or the reservation on screen. Never includes name or contact data.
     */
    function uiContext() {
        const [page, param] = (window.location.hash.slice(1) || 'home').split('/');
        const context = { scene: sceneNames[page] || 'reception' };

        if (page === 'facility' && Number(param) > 0) context.selected_facility = Number(param);

        if ((page === 'room' || page === 'reservation') && param) context.selected_room = param;

        if (page === 'reservation') {
            try {
                const draft = JSON.parse(sessionStorage.getItem(`reservation_draft_${root.dataset.hotelSlug}`) || 'null');
                const { check_in, check_out, adults, children, rooms, room_type_slug } = draft?.values || {};
                context.reservation = { check_in, check_out, adults, children, rooms, room_type_slug };
                if (!param && room_type_slug) context.selected_room = room_type_slug;
            } catch { /* no draft to share */ }
        }

        return context;
    }

    function actionChips(actions) {
        if (!actions || !actions.length) return null;

        const targets = {
            view_room: [config.labels.viewDetails, (action) => `#room/${action.room}`],
            reserve: [config.labels.bookNow, (action) => `#reservation/${action.room}`],
            staff: [config.labels.staff, () => '#staff'],
        };

        const wrap = document.createElement('div');
        wrap.className = 'flex flex-wrap gap-2 pl-10';

        actions.forEach((action) => {
            const target = targets[action.action];
            if (!target) return;
            const link = document.createElement('a');
            link.href = target[1](action);
            link.className = 'rounded-full border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 hover:bg-stone-50';
            link.textContent = target[0];
            link.addEventListener('click', closePanel);
            wrap.appendChild(link);
        });

        return wrap.childElementCount ? wrap : null;
    }

    function errorCard(text, err) {
        const el = document.createElement('div');
        el.className = 'rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900';

        const message = document.createElement('p');
        message.textContent = err.status === 429 ? config.labels.slow : config.labels.error;

        const actions = document.createElement('div');
        actions.className = 'mt-3 flex flex-wrap gap-2';

        const retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'rounded-full bg-stone-900 px-3 py-1.5 text-xs font-medium text-white';
        retry.textContent = config.labels.retry;
        retry.addEventListener('click', () => {
            el.remove();
            sendMessage(text, { retry: true });
        });

        const staff = document.createElement('a');
        staff.href = '#staff';
        staff.className = 'rounded-full border border-rose-300 bg-white px-3 py-1.5 text-xs font-medium text-rose-900';
        staff.textContent = config.labels.staff;
        staff.addEventListener('click', closePanel);

        actions.append(retry, staff);
        el.append(message, actions);
        return el;
    }

    async function sendMessage(text, { retry = false } = {}) {
        if (!text.trim() || handedOver || busy || !ready) return;

        const guestToken = localStorage.getItem(config.storageKey);
        if (!guestToken) return;

        openPanel();
        if (!retry) messagesEl.appendChild(bubble('guest', text));
        window.hotelSound?.play('sent');
        scrollToBottom();
        setBusy(true);

        try {
            const data = await api(config.messageUrl, { guest_token: guestToken, message: text, ...uiContext() });
            renderMessage(data.message);
            window.hotelSound?.play('incoming');
            knownMessageCount += 2; // the guest message just sent + the reply just rendered
            if (data.status === 'handed_over') {
                showHandedOverBanner();
            }
        } catch (err) {
            messagesEl.appendChild(errorCard(text, err));
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
        if (!ready || busy || handedOver || !inputEl.value.trim()) return;
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

    setBusy(true);
    boot().then(() => {
        ready = true;
        scrollToBottom();
    }).catch(() => {
        statusBanner.textContent = root.dataset.labelConnectionError;
        statusBanner.classList.remove('hidden');
        messagesEl.appendChild(bubble('assistant', config.labels.intro));
    }).finally(() => setBusy(false));
}

document.addEventListener('DOMContentLoaded', initConcierge);


function initLobbyNavigation() {
    const panels = [...document.querySelectorAll('[data-lobby-panel]')];
    if (!panels.length) return;
    const links = [...document.querySelectorAll('[data-lobby-link]')];

    const scenes = [...document.querySelectorAll('[data-room-scene], [data-facility-scene]')];
    const sceneId = (scene) => scene.dataset.roomScene ?? scene.dataset.facilityScene;
    const sceneParents = { room: 'rooms', facility: 'facilities' };

    function showPage(focus = false) {
        const [requested, routeParam] = (window.location.hash.slice(1) || 'home').split('/');
        let page = panels.some((panel) => panel.dataset.lobbyPanel === requested) ? requested : 'home';
        const activeScene = page in sceneParents
            ? scenes.find((scene) => sceneId(scene) === routeParam && (page === 'room') === ('roomScene' in scene.dataset))
            : null;
        if (page in sceneParents && !activeScene) page = sceneParents[page];

        document.querySelector('[data-lobby]')?.setAttribute('data-page', page);
        panels.forEach((panel) => { panel.hidden = panel.dataset.lobbyPanel !== page; });
        scenes.forEach((scene) => { scene.hidden = scene !== activeScene; });

        if (page === 'reservation' && routeParam) {
            window.dispatchEvent(new CustomEvent('reservation:preselect', { detail: routeParam }));
        }

        // Room and facility scenes belong to their list's journey, so keep that nav item active.
        const activeLink = sceneParents[page] ?? page;
        links.forEach((link) => {
            if (link.dataset.lobbyLink === activeLink) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
        if (focus && page !== 'home') {
            panels.find((panel) => panel.dataset.lobbyPanel === page)?.focus({ preventScroll: true });
        }
    }

    window.addEventListener('hashchange', () => showPage(true));
    showPage();
}

document.addEventListener('DOMContentLoaded', initLobbyNavigation);

function initRoomGallery() {
    document.querySelectorAll('[data-room-gallery]').forEach((gallery) => {
        const main = gallery.querySelector('[data-room-gallery-main]');
        const thumbs = [...gallery.querySelectorAll('[data-room-thumb]')];
        if (!main) return;

        thumbs.forEach((thumb) => {
            thumb.addEventListener('click', () => {
                main.src = thumb.dataset.src;
                main.alt = thumb.dataset.alt || '';
                thumbs.forEach((other) => other.toggleAttribute('aria-current', other === thumb));
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', initRoomGallery);
