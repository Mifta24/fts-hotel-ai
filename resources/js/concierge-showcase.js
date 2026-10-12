/**
 * The showcase: where the concierge presents rooms and the reservation form to
 * the guest, in the middle of the stage instead of inside the narrow chat
 * column. One thing is shown at a time; a form opened from the rooms keeps a
 * way back to them. On a phone it is a sheet: dragging its handle down
 * dismisses it, and taps give a light haptic. While it is up the rest of the stage steps back: the
 * transcript is tucked away and what is behind is dimmed. Closing it never
 * loses what the guest typed, because the form is only detached, not discarded.
 */
import { haptic, PRESS, TAP } from './haptics';

const CLOSE_DELAY_MS = 320;
const DISMISS_DISTANCE = 96;
const DISMISS_VELOCITY = 0.55;

function node(tag, className, attrs = {}) {
    const el = document.createElement(tag);
    el.className = className;
    Object.entries(attrs).forEach(([key, value]) => el.setAttribute(key, value));

    return el;
}

/**
 * @param {object} options
 * @param {Element} options.stage       the stage the showcase floats over
 * @param {string} options.closeLabel
 * @param {string} options.backLabel
 */
export function createShowcase({ stage, closeLabel, backLabel }) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const history = [];
    let panel = null;
    let scrim = null;
    let bubble;
    let tray;
    let body;
    let back;
    let current = null;

    function build() {
        scrim = node('div', 'showcase-scrim', { 'aria-hidden': 'true' });
        scrim.addEventListener('click', () => hide());
        panel = node('section', 'showcase', { role: 'region', tabindex: '-1' });
        bubble = node('p', 'showcase-bubble');
        const frame = node('div', 'showcase-frame');
        body = node('div', 'showcase-body');

        const close = node('button', 'showcase-btn showcase-close', { type: 'button', 'aria-label': closeLabel });
        close.textContent = '×';
        close.addEventListener('click', () => hide());

        back = node('button', 'showcase-btn showcase-back', { type: 'button' });
        back.textContent = `‹ ${backLabel}`;
        back.addEventListener('click', goBack);

        const grip = node('div', 'showcase-grip', { 'aria-hidden': 'true' });
        grip.addEventListener('pointerdown', startDrag);

        // One light tap for any button pressed inside the showcase.
        panel.addEventListener('click', (event) => {
            if (event.target.closest('button, a')) haptic(TAP);
        });

        frame.append(body, back, close, grip);
        tray = node('div', 'showcase-chips');
        panel.append(bubble, frame, tray);
        stage.append(scrim, panel);
        requestAnimationFrame(() => requestAnimationFrame(() => {
            scrim?.classList.add('is-in');
            panel?.classList.add('is-in');
        }));
    }

    function render(content, comment, chips) {
        current = { content, comment, chips };
        bubble.textContent = comment || '';
        bubble.hidden = !comment;
        body.replaceChildren(content);
        tray.replaceChildren(...(chips ? [chips] : []));
        tray.hidden = !chips;
        body.scrollTop = 0;
        back.hidden = history.length === 0;
        panel.setAttribute('aria-label', comment || content.getAttribute?.('aria-label') || '');
    }

    /** Dragging the handle moves the sheet with the finger; a long or quick pull down lets it go. */
    function startDrag(event) {
        if (!panel || event.button > 0) return;

        const sheet = panel;
        const grip = event.currentTarget;
        const startY = event.clientY;
        const startedAt = performance.now();
        let distance = 0;

        grip.setPointerCapture(event.pointerId);
        sheet.classList.add('is-dragging');

        const move = (moveEvent) => {
            distance = Math.max(0, moveEvent.clientY - startY);
            sheet.style.transform = `translateY(${distance}px)`;
            sheet.style.opacity = String(Math.max(0.35, 1 - distance / 420));
        };

        const end = () => {
            grip.removeEventListener('pointermove', move);
            grip.removeEventListener('pointerup', end);
            grip.removeEventListener('pointercancel', end);
            sheet.classList.remove('is-dragging');

            const velocity = distance / Math.max(performance.now() - startedAt, 1);

            if (distance > DISMISS_DISTANCE || (distance > 24 && velocity > DISMISS_VELOCITY)) {
                haptic(PRESS);
                hide({ swiped: true });
            } else {
                sheet.style.transform = '';
                sheet.style.opacity = '';
            }
        };

        grip.addEventListener('pointermove', move);
        grip.addEventListener('pointerup', end);
        grip.addEventListener('pointercancel', end);
    }

    function goBack() {
        const previous = history.pop();
        if (previous) render(previous.content, previous.comment, previous.chips);
    }

    /**
     * @param {Element} content
     * @param {{comment?: string, back?: boolean}} options
     *        comment: what the concierge says above the cards.
     *        chips: the tappable answers to show beneath them.
     *        back: this opens from what is on show, so keep that to return to.
     */
    function show(content, { comment = '', chips = null, back: keep = false } = {}) {
        if (panel && keep && current) history.push(current);
        else history.length = 0;

        if (!panel) build();

        render(content, comment, chips);
        panel.focus({ preventScroll: true });
    }

    function hide({ swiped = false } = {}) {
        if (!panel) return;

        const closing = [panel, scrim];
        if (swiped) panel.style.transform = 'translateY(110%)';
        panel = null;
        scrim = null;
        current = null;
        history.length = 0;
        closing.forEach((el) => el.classList.remove('is-in'));

        if (reducedMotion) closing.forEach((el) => el.remove());
        else setTimeout(() => closing.forEach((el) => el.remove()), CLOSE_DELAY_MS);
    }

    return { show, hide, isOpen: () => panel !== null };
}
