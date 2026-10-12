/**
 * The tappable cards the AI concierge shows in the chat: a rail of room cards
 * (photo, details that open in place, a reserve button), the reservation form
 * the guest can finish by tapping, and the confirmation that follows. Prices
 * and availability always come from the server; nothing here decides them.
 *
 * Everything is built with DOM nodes and textContent, never HTML strings, so
 * room names and anything the guest typed cannot inject markup.
 */
import { calendarFile } from './concierge-celebrate';
import { DONE, haptic } from './haptics';

const PHONE_PATTERN = /^\+?[0-9\s\-().]{6,20}$/;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const CONTACT_TYPES = ['whatsapp', 'phone', 'email'];

function h(tag, props = {}, ...children) {
    const el = document.createElement(tag);

    for (const [key, value] of Object.entries(props)) {
        if (value == null || value === false) continue;

        if (key === 'class') el.className = value;
        else if (key === 'text') el.textContent = value;
        else if (key.startsWith('on')) el.addEventListener(key.slice(2).toLowerCase(), value);
        else if (key in el) el[key] = value;
        else el.setAttribute(key, value === true ? '' : value);
    }

    el.append(...children.flat().filter((child) => child != null && child !== false));

    return el;
}

const svgCheck = () => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M5 12.5l4.2 4.2L19 7');
    svg.appendChild(path);

    return svg;
};

const isoDate = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

const nightsBetween = (from, to) => Math.round((new Date(`${to}T00:00:00`) - new Date(`${from}T00:00:00`)) / 86400000);

const dayAfter = (iso) => {
    const next = new Date(`${iso}T00:00:00`);
    next.setDate(next.getDate() + 1);

    return isoDate(next);
};

/**
 * @param {object} ctx
 * @param {object} ctx.config      the chat's config (locale, labels, urls)
 * @param {object} ctx.wizard      the reservation wizard's labels, in the guest's language
 * @param {object} ctx.terms       bed / view / amenity names, in the guest's language
 * @param {(value: number) => string} ctx.money
 * @param {(url: string, body: object) => Promise<any>} ctx.api
 * @param {(text: string) => void} ctx.ask          sends a message as the guest
 * @param {(node: Element) => void} ctx.append      adds a card to the transcript
 * @param {(node: Element, options?: {back?: boolean}) => void} ctx.present  puts a card in the showcase
 * @param {() => void} ctx.celebrate               a reservation request just went through
 * @param {(options: object) => Element} ctx.picker   builds the calendar card the concierge offers for dates
 * @param {() => void} ctx.recorded                 a message was saved server-side that the chat did not receive
 */
export function createCards(ctx) {
    const { config, wizard, terms, money, api } = ctx;
    const { labels } = config;
    const numberLocale = config.locale === 'ja' ? 'ja-JP' : config.locale === 'en' ? 'en-US' : 'id-ID';
    const rooms = new Map();
    const openForms = new Map();

    const text = (template, values) => Object.entries(values).reduce((out, [key, value]) => out.replace(`:${key}`, value), template);
    const day = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString(numberLocale, { day: 'numeric', month: 'short' });
    const guests = (room) => `${room.max_adults + room.max_children} ${labels.maxGuests}`;
    const beds = (room) => (room.bed_config || []).map((bed) => `${bed.count} × ${terms.bed?.[bed.type] || bed.type}`).join(', ');
    const view = (room) => (room.view_type ? terms.view?.[room.view_type] || `${room.view_type} ${labels.viewSuffix}` : '');
    const amenity = (key) => terms.amenity?.[key] || key.replace(/_/g, ' ');
    const photos = (room) => (room.images?.length ? room.images : room.thumbnail_url ? [{ url: room.thumbnail_url, alt: room.name }] : []);

    function priceLine(room, stay) {
        if (stay && room.total_price != null) {
            return h('p', { class: 'rc-price' }, money(room.total_price), h('span', { text: `${stay.nights ?? room.nights} ${labels.night}` }));
        }

        return h('p', { class: 'rc-price' },
            labels.from ? h('span', { class: 'rc-from', text: labels.from }) : null,
            money(room.starting_price),
            h('span', { text: labels.perNight }));
    }

    /** The photo walks to the room's own page, growing into the big photo there. */
    function openRoomPage(event, photo) {
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;

        event.preventDefault();
        const href = event.currentTarget.href;

        if (window.hotelStage) window.hotelStage.leave(href, null, null, { photo: photo.isConnected && photo.naturalWidth ? photo : null });
        else window.location.href = href;
    }

    function roomCard(room, { index = 0, stay = null, expanded = false } = {}) {
        rooms.set(room.room_type_slug, room);

        const gallery = photos(room);
        const photo = h('img', {
            class: 'rc-photo-img',
            src: gallery[0]?.url,
            alt: gallery[0]?.alt || room.name,
            loading: 'lazy',
            onLoad: (event) => event.target.classList.add('is-loaded'),
            onError: (event) => event.target.remove(),
        });

        const thumbs = gallery.length > 1
            ? h('div', { class: 'rc-thumbs' }, gallery.map((image, position) => h('button', {
                type: 'button',
                'aria-label': `${room.name} ${position + 1}`,
                'aria-current': position === 0 ? 'true' : null,
                onClick: (event) => {
                    photo.src = image.url;
                    photo.alt = image.alt || room.name;
                    event.currentTarget.parentElement.querySelectorAll('button').forEach((other) => other.toggleAttribute('aria-current', other === event.currentTarget));
                },
            }, h('img', { src: image.url, alt: '', loading: 'lazy' }))))
            : null;

        const details = h('div', { class: `rc-details${expanded ? ' is-open' : ''}`, inert: !expanded },
            h('div', { class: 'rc-details-inner' },
                room.description ? h('p', { class: 'rc-desc', text: room.description }) : null,
                beds(room) ? h('p', { class: 'rc-fact', text: beds(room) }) : null,
                room.amenities?.length ? h('ul', { class: 'rc-chips' }, room.amenities.map((key) => h('li', { text: amenity(key) }))) : null,
                thumbs));

        const toggle = h('button', {
            type: 'button',
            class: 'rc-btn',
            'aria-expanded': String(expanded),
            text: expanded ? labels.hideDetails : labels.viewDetails,
            onClick: () => {
                const open = !details.classList.contains('is-open');
                details.classList.toggle('is-open', open);
                details.inert = !open;
                toggle.setAttribute('aria-expanded', String(open));
                toggle.textContent = open ? labels.hideDetails : labels.viewDetails;
            },
        });

        return h('article', { class: 'rc', style: `--i:${index}`, role: 'listitem' },
            h('a', {
                class: 'rc-photo',
                href: config.roomUrlTemplate.replace('__SLUG__', encodeURIComponent(room.room_type_slug)),
                'aria-label': room.name,
                onClick: (event) => openRoomPage(event, photo),
            },
                gallery.length ? photo : h('span', { class: 'rc-photo-empty', 'aria-hidden': 'true' }),
                h('span', { class: 'rc-photo-cue', 'aria-hidden': 'true', text: '↗' }),
                h('div', { class: 'rc-badges' },
                    view(room) ? h('span', { text: view(room) }) : null,
                    room.breakfast_included ? h('span', { text: labels.breakfastIncluded }) : null)),
            h('div', { class: 'rc-body' },
                h('h4', { class: 'rc-name', text: room.name }),
                h('p', { class: 'rc-meta', text: [room.size_sqm ? `${room.size_sqm} m²` : null, guests(room)].filter(Boolean).join(' · ') }),
                priceLine(room, stay),
                details,
                h('div', { class: 'rc-actions' },
                    toggle,
                    h('button', {
                        type: 'button',
                        class: 'rc-btn rc-btn-primary',
                        text: labels.bookNow,
                        onClick: () => openForm({ room, values: stay ? { check_in: stay.check_in, check_out: stay.check_out } : {} }),
                    }))));
    }

    function roomRail(items, stay) {
        const rail = h('div', { class: 'rc-rail', tabindex: items.length > 2 ? 0 : null, role: 'list' },
            items.map((room, index) => roomCard(room, { index, stay: stay && { ...stay, nights: room.nights } })));

        if (items.length < 3) return rail;

        const arrow = (direction, symbol) => h('button', {
            type: 'button',
            class: `rc-arrow is-${direction}`,
            tabindex: -1,
            'aria-hidden': 'true',
            text: symbol,
            onClick: () => rail.scrollBy({ left: (direction === 'next' ? 1 : -1) * rail.clientWidth * 0.8, behavior: 'smooth' }),
        });

        return h('div', { class: 'rc-rail-wrap' }, rail, arrow('prev', '‹'), arrow('next', '›'));
    }

    function availabilityCard(payload) {
        if (!payload.available) {
            return h('div', { class: 'rc-note', text: labels.noAvailability });
        }

        const quote = payload.quote;
        const room = payload.room || { room_type_slug: quote.room_type_slug, name: quote.name };
        rooms.set(room.room_type_slug, room);

        return h('div', { class: 'rc-note rc-avail' },
            h('p', { class: 'rc-name', text: quote.name }),
            h('p', { class: 'rc-meta', text: `${day(quote.check_in)} → ${day(quote.check_out)} · ${quote.nights} ${labels.night}` }),
            h('p', { class: 'rc-price' }, money(quote.grand_total)),
            h('button', {
                type: 'button',
                class: 'rc-btn rc-btn-primary',
                text: labels.bookNow,
                onClick: () => openForm({ room, values: { check_in: quote.check_in, check_out: quote.check_out } }),
            }));
    }

    function confirmationCard(payload) {
        const booking = payload.booking;
        const whatsapp = payload.handover?.whatsapp_url;

        return h('div', { class: 'rc-done' },
            h('div', { class: 'rc-done-head' },
                h('span', { class: 'rc-done-check' }, svgCheck()),
                h('div', {},
                    h('p', { class: 'rc-done-title', text: labels.bookingReceived }),
                    h('p', { class: 'rc-done-sub', text: wizard.awaiting }))),
            h('dl', { class: 'rc-done-rows' },
                h('div', {}, h('dt', { text: wizard.reference }), h('dd', { class: 'rc-ref', text: booking.booking_reference })),
                h('div', {}, h('dt', { text: wizard.room }), h('dd', { text: booking.name })),
                h('div', {}, h('dt', { text: wizard.dates }), h('dd', { text: `${day(booking.check_in)} → ${day(booking.check_out)} · ${booking.nights} ${labels.night}` })),
                h('div', {}, h('dt', { text: wizard.estimated_total }), h('dd', { class: 'rc-total', text: money(booking.total_price) }))),
            whatsapp ? h('a', { class: 'rc-btn rc-btn-primary rc-btn-wide', href: whatsapp, target: '_blank', rel: 'noopener', text: wizard.send_whatsapp }) : null,
            h('div', { class: 'rc-done-actions' },
                h('button', { type: 'button', class: 'rc-btn', text: labels.addCalendar, onClick: () => saveToCalendar(booking) }),
                h('button', { type: 'button', class: 'rc-btn', text: labels.share, onClick: () => shareStay(booking) })));
    }

    function saveToCalendar(booking) {
        const link = h('a', { href: URL.createObjectURL(calendarFile(booking, config.hotelName)), download: `${booking.booking_reference}.ics` });
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 4000);
    }

    /** The system share sheet where there is one; WhatsApp otherwise. */
    function shareStay(booking) {
        const message = labels.shareText
            .replace(':hotel', config.hotelName)
            .replace(':room', booking.name)
            .replace(':in', day(booking.check_in))
            .replace(':out', day(booking.check_out))
            .replace(':ref', booking.booking_reference);

        if (navigator.share) navigator.share({ text: message }).catch(() => {});
        else window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank', 'noopener');
    }

    /**
     * The reservation form for a room. One that is still unfinished is handed
     * back as it was (with what the guest already typed) instead of a second.
     */
    function formFor({ room, values = {} }) {
        const existing = openForms.get(room.room_type_slug);

        if (existing && !existing.dataset.done) return existing;

        rooms.set(room.room_type_slug, room);
        const form = bookingForm({ room, values });
        openForms.set(room.room_type_slug, form);

        return form;
    }

    /** Opens the form in the showcase, with a way back to what was being shown. */
    function openForm(details) {
        ctx.present(formFor(details), { back: true });
    }

    function bookingForm({ room, values = {} }) {
        const maxAdults = room.max_adults || 20;
        const maxChildren = room.max_children ?? 10;
        const state = {
            check_in: values.check_in || '',
            check_out: values.check_out || '',
            adults: Math.min(Math.max(Number(values.adults) || 2, 1), maxAdults),
            children: Math.min(Math.max(Number(values.children) || 0, 0), maxChildren),
            guest_name: values.guest_name || '',
            contact_type: 'whatsapp',
            contact_value: values.contact_value || '',
        };
        const fields = {};
        let quoteTicket = 0;
        let quoteTimer = null;
        let submitting = false;

        const root = h('form', { class: 'rf', novalidate: true, 'aria-label': text(labels.formTitle, { room: room.name }) });
        const summary = h('p', { class: 'rf-summary', 'aria-live': 'polite' });
        const alternatives = h('div', { class: 'rf-alts' });
        const formError = h('p', { class: 'rf-error', role: 'alert', hidden: true });
        const submit = h('button', { type: 'submit', class: 'rc-btn rc-btn-primary rc-btn-wide', text: labels.formConfirm });

        function field(name, label, input) {
            const message = h('span', { class: 'rf-msg', role: 'alert' });
            fields[name] = { input, message };

            return h('label', { class: 'rf-field' }, h('span', { class: 'rf-label', text: label }), input, message);
        }

        function markInvalid(name, message) {
            const target = fields[name];
            if (!target) return false;

            target.input.classList.add('is-invalid');
            target.message.textContent = message;

            return true;
        }

        function clearErrors() {
            Object.values(fields).forEach(({ input, message }) => {
                input.classList.remove('is-invalid');
                message.textContent = '';
            });
            formError.hidden = true;
            formError.textContent = '';
        }

        function stepper(name, label, min, max) {
            const value = h('output', { class: 'rf-step-value', text: String(state[name]) });
            const set = (next) => {
                state[name] = Math.min(Math.max(next, min), max);
                value.textContent = String(state[name]);
                minus.disabled = state[name] <= min;
                plus.disabled = state[name] >= max;
                scheduleQuote();
            };
            const minus = h('button', { type: 'button', class: 'rf-step-btn', 'aria-label': `− ${label}`, text: '−', onClick: () => set(state[name] - 1) });
            const plus = h('button', { type: 'button', class: 'rf-step-btn', 'aria-label': `+ ${label}`, text: '+', onClick: () => set(state[name] + 1) });
            minus.disabled = state[name] <= min;
            plus.disabled = state[name] >= max;

            return h('div', { class: 'rf-stepper' }, h('span', { class: 'rf-label', text: label }), h('div', {}, minus, value, plus));
        }

        const checkIn = h('input', { type: 'date', name: 'check_in', min: config.today, value: state.check_in, required: true });
        const checkOut = h('input', { type: 'date', name: 'check_out', min: config.today, value: state.check_out, required: true });
        const nameInput = h('input', { type: 'text', name: 'guest_name', autocomplete: 'name', maxLength: 100, value: state.guest_name, required: true });
        const contactInput = h('input', { type: 'tel', name: 'contact_value', autocomplete: 'tel', maxLength: 120, value: state.contact_value, required: true });

        const contactChips = h('div', { class: 'rf-chips', role: 'group', 'aria-label': wizard.contact_method },
            CONTACT_TYPES.map((type) => h('button', {
                type: 'button',
                'data-contact': type,
                'aria-pressed': String(type === state.contact_type),
                text: wizard[type],
                onClick: () => {
                    state.contact_type = type;
                    contactInput.type = type === 'email' ? 'email' : 'tel';
                    contactInput.autocomplete = type === 'email' ? 'email' : 'tel';
                    contactChips.querySelectorAll('button').forEach((chip) => chip.setAttribute('aria-pressed', String(chip.dataset.contact === type)));
                },
            })));

        checkIn.addEventListener('input', () => {
            state.check_in = checkIn.value;
            checkOut.min = checkIn.value ? dayAfter(checkIn.value) : config.today;
            if (checkOut.value && checkIn.value && checkOut.value <= checkIn.value) {
                checkOut.value = dayAfter(checkIn.value);
                state.check_out = checkOut.value;
            }
            scheduleQuote();
        });
        checkOut.addEventListener('input', () => {
            state.check_out = checkOut.value;
            scheduleQuote();
        });
        nameInput.addEventListener('input', () => (state.guest_name = nameInput.value));
        contactInput.addEventListener('input', () => (state.contact_value = contactInput.value));
        if (state.check_in) checkOut.min = dayAfter(state.check_in);

        const thumb = photos(room)[0];
        root.append(
            h('header', { class: 'rf-head' },
                thumb ? h('img', { src: thumb.url, alt: '', loading: 'lazy' }) : h('span', { class: 'rc-photo-empty', 'aria-hidden': 'true' }),
                h('div', {},
                    h('p', { class: 'rf-title', text: text(labels.formTitle, { room: room.name }) }),
                    room.starting_price != null ? h('p', { class: 'rf-sub' }, [labels.from, money(room.starting_price), labels.perNight].filter(Boolean).join(' ')) : null)),
            h('div', { class: 'rf-grid' },
                field('check_in', wizard.check_in, checkIn),
                field('check_out', wizard.check_out, checkOut)),
            h('div', { class: 'rf-grid' },
                stepper('adults', wizard.adults, 1, maxAdults),
                stepper('children', wizard.children, 0, maxChildren)),
            field('guest_name', wizard.name, nameInput),
            h('div', { class: 'rf-field' },
                h('span', { class: 'rf-label', text: wizard.contact_method }),
                contactChips,
                field('contact_value', wizard.contact_value, contactInput)),
            summary,
            alternatives,
            formError,
            submit,
            h('p', { class: 'rf-disclaimer', text: wizard.disclaimer }),
        );

        function datesError() {
            if (!state.check_in || state.check_in < config.today) return ['check_in', wizard.check_in_past];
            if (!state.check_out || state.check_out <= state.check_in) return ['check_out', wizard.check_out_after];
            if (nightsBetween(state.check_in, state.check_out) > config.maxNights) return ['check_out', text(wizard.too_long, { max: config.maxNights })];

            return null;
        }

        function stayBody() {
            return { room_type_slug: room.room_type_slug, check_in: state.check_in, check_out: state.check_out, adults: state.adults, children: state.children, rooms: 1, locale: config.locale };
        }

        function scheduleQuote() {
            clearTimeout(quoteTimer);
            alternatives.replaceChildren();
            quoteTicket++;

            if (datesError()) {
                summary.textContent = '';

                return;
            }

            summary.textContent = wizard.checking;
            quoteTimer = setTimeout(refreshQuote, 350);
        }

        async function refreshQuote() {
            const ticket = ++quoteTicket;

            try {
                const data = await api(config.quoteUrl, stayBody());
                if (ticket !== quoteTicket) return;

                summary.textContent = `${wizard.estimated_total}: ${money(data.grand_total)} · ${data.nights} ${labels.night}`;
            } catch (error) {
                if (ticket !== quoteTicket) return;

                summary.textContent = '';
                showFailure(error);
            }
        }

        function showFailure(error) {
            const payload = error.payload || {};
            const failures = Object.entries(payload.errors || {});

            const say = (message) => {
                formError.textContent = message;
                formError.hidden = false;
            };

            failures.forEach(([name, messages]) => {
                if (name === 'room_type_slug' || !markInvalid(name, messages[0])) say(messages[0]);
            });

            if (!failures.length) {
                say(error.status === 429 ? labels.slow : error.status === 0 || error.status === 408 ? wizard.error_network : payload.message || wizard.error_generic);
            }

            if (payload.alternatives?.length) {
                alternatives.replaceChildren(
                    h('p', { class: 'rf-alts-title', text: wizard.available_instead }),
                    h('div', { class: 'rf-chips' }, payload.alternatives.map((alt) => h('button', {
                        type: 'button',
                        text: `${alt.name} · ${money(alt.total)}`,
                        onClick: () => {
                            const known = rooms.get(alt.slug);
                            if (known) openForm({ room: known, values: { ...state } });
                            else ctx.ask(text(labels.roomDetailsQuestion, { room: alt.name }));
                        },
                    }))));
            }
        }

        function validate() {
            const dates = datesError();
            if (dates) return dates;
            if (!state.guest_name.trim()) return ['guest_name', wizard.invalid];

            const contact = state.contact_value.trim();
            const valid = state.contact_type === 'email' ? EMAIL_PATTERN.test(contact) : PHONE_PATTERN.test(contact);

            return valid ? null : ['contact_value', state.contact_type === 'email' ? wizard.contact_email : wizard.contact_phone];
        }

        root.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (submitting) return;

            clearErrors();
            const problem = validate();

            if (problem) {
                markInvalid(problem[0], problem[1]);
                fields[problem[0]].input.focus();

                return;
            }

            submitting = true;
            submit.disabled = true;
            submit.textContent = wizard.sending;
            clearTimeout(quoteTimer);

            try {
                const data = await api(config.reserveUrl, {
                    ...stayBody(),
                    guest_name: state.guest_name.trim(),
                    contact_type: state.contact_type,
                    contact_value: state.contact_value.trim(),
                    guest_token: localStorage.getItem(config.storageKey) || undefined,
                    via_chat: true,
                });

                const confirmation = data.confirmation || {
                    type: 'booking_confirmation',
                    booking: {
                        booking_reference: data.reference,
                        name: room.name,
                        check_in: state.check_in,
                        check_out: state.check_out,
                        nights: nightsBetween(state.check_in, state.check_out),
                        total_price: data.total,
                    },
                    handover: data.handover,
                };

                root.dataset.done = 'true';
                root.className = 'rf-done';
                root.replaceChildren(confirmationCard(confirmation));
                ctx.append(confirmationCard(confirmation));
                ctx.celebrate();
                haptic(DONE);
                window.hotelSound?.play('incoming');
                if (data.confirmation) ctx.recorded();
            } catch (error) {
                showFailure(error);
                submitting = false;
                submit.disabled = false;
                submit.textContent = labels.formConfirm;
            }
        });

        if (!datesError()) {
            summary.textContent = wizard.checking;
            refreshQuote();
        }

        return root;
    }

    /**
     * What the concierge puts in the showcase in the middle of the screen: the
     * rooms, one room, or a reservation form. `recap` is the one line the
     * transcript keeps, and `build` makes the content afresh each time it is shown.
     *
     * @param {object} payload  one entry of a message's ui_payload
     * @returns {{recap: string, build: () => Element}|null}
     */
    function describe(payload) {
        switch (payload.type) {
            case 'room_results':
                return {
                    recap: text(labels.showingRooms, { count: payload.rooms.length }),
                    build: () => roomRail(payload.rooms, payload.check_in ? { check_in: payload.check_in, check_out: payload.check_out } : null),
                };
            case 'room_detail':
                return { recap: payload.room.name, build: () => roomCard(payload.room, { expanded: true }) };
            case 'stay_picker':
                if (payload.room) rooms.set(payload.room.room_type_slug, payload.room);

                return { recap: labels.pickerTitle, build: () => ctx.picker({ room: payload.room, values: payload.values }) };
            case 'booking_form':
                rooms.set(payload.room.room_type_slug, payload.room);

                return { recap: text(labels.formTitle, { room: payload.room.name }), build: () => formFor(payload) };
            default:
                return null;
        }
    }

    /**
     * What stays in the transcript itself: the smaller cards.
     *
     * @param {object} payload  one entry of a message's ui_payload
     * @returns {Element|null}
     */
    function render(payload) {
        switch (payload.type) {
            case 'availability':
                return availabilityCard(payload);
            case 'booking_confirmation':
                return confirmationCard(payload);
            default:
                return null;
        }
    }

    /** Placeholder cards that shimmer while the concierge is still finding the rooms. */
    function skeleton() {
        return h('div', { class: 'rc-rail', 'aria-hidden': 'true' }, [0, 1].map((index) => h('article', { class: 'rc rc-skeleton', style: `--i:${index}` },
            h('div', { class: 'rc-photo' }),
            h('div', { class: 'rc-body' },
                h('span', { class: 'rc-line is-wide' }),
                h('span', { class: 'rc-line is-mid' }),
                h('span', { class: 'rc-line is-short' }),
                h('div', { class: 'rc-actions' }, h('span', { class: 'rc-pill' }), h('span', { class: 'rc-pill' }))))));
    }

    return { describe, render, skeleton };
}
