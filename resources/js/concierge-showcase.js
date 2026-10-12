/**
 * The showcase: where the concierge presents rooms and the reservation form to
 * the guest, in the middle of the stage instead of inside the narrow chat
 * column. One thing is shown at a time; a form opened from the rooms keeps a
 * way back to them. Closing it never loses what the guest typed, because the
 * form is only detached, not discarded.
 */
const CLOSE_DELAY_MS = 320;

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
    let bubble;
    let body;
    let back;
    let current = null;

    function build() {
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

        frame.append(body, back, close);
        panel.append(bubble, frame);
        stage.appendChild(panel);
        requestAnimationFrame(() => requestAnimationFrame(() => panel?.classList.add('is-in')));
    }

    function render(content, comment) {
        current = { content, comment };
        bubble.textContent = comment || '';
        bubble.hidden = !comment;
        body.replaceChildren(content);
        body.scrollTop = 0;
        back.hidden = history.length === 0;
        panel.setAttribute('aria-label', comment || content.getAttribute?.('aria-label') || '');
    }

    function goBack() {
        const previous = history.pop();
        if (previous) render(previous.content, previous.comment);
    }

    /**
     * @param {Element} content
     * @param {{comment?: string, back?: boolean}} options
     *        comment: what the concierge says above the cards.
     *        back: this opens from what is on show, so keep that to return to.
     */
    function show(content, { comment = '', back: keep = false } = {}) {
        if (panel && keep && current) history.push(current);
        else history.length = 0;

        if (!panel) build();

        render(content, comment);
        panel.focus({ preventScroll: true });
    }

    function hide() {
        if (!panel) return;

        const closing = panel;
        panel = null;
        current = null;
        history.length = 0;
        closing.classList.remove('is-in');

        if (reducedMotion) closing.remove();
        else setTimeout(() => closing.remove(), CLOSE_DELAY_MS);
    }

    return { show, hide, isOpen: () => panel !== null };
}
