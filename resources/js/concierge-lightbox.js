/**
 * A full-screen photo viewer for the photos the concierge shows in chat: swipe,
 * arrow keys or the arrow buttons to move, Escape or a tap outside to close.
 * Built with DOM nodes and textContent only, so captions cannot inject markup.
 */
import { haptic, TAP } from './haptics';

let current = null;

/**
 * @param {Array<{url: string, alt?: string, caption?: string}>} photos
 * @param {number} start  index of the photo to open on
 * @param {{prev: string, next: string, close: string}} labels
 */
export function openLightbox(photos, start, labels) {
    if (!photos.length) return;

    current?.close();

    const opener = document.activeElement;
    let index = Math.min(Math.max(start, 0), photos.length - 1);
    let touchX = null;

    const button = (className, symbol, label, onClick) => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = `lb-btn ${className}`;
        el.textContent = symbol;
        el.setAttribute('aria-label', label);
        el.addEventListener('click', (event) => {
            event.stopPropagation();
            onClick();
        });

        return el;
    };

    const image = document.createElement('img');
    image.className = 'lb-img';
    const caption = document.createElement('p');
    caption.className = 'lb-caption';
    const counter = document.createElement('p');
    counter.className = 'lb-counter';
    counter.setAttribute('aria-live', 'polite');

    const root = document.createElement('div');
    root.className = 'lb';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');

    function show(next) {
        index = (next + photos.length) % photos.length;
        const photo = photos[index];
        image.src = photo.url;
        image.alt = photo.alt || photo.caption || '';
        caption.textContent = photo.caption || '';
        counter.textContent = photos.length > 1 ? `${index + 1} / ${photos.length}` : '';
    }

    function close() {
        document.removeEventListener('keydown', onKey, true);
        root.remove();
        current = null;
        if (opener?.isConnected) opener.focus({ preventScroll: true });
    }

    function onKey(event) {
        if (event.key === 'Escape') close();
        else if (event.key === 'ArrowRight' && photos.length > 1) show(index + 1);
        else if (event.key === 'ArrowLeft' && photos.length > 1) show(index - 1);
        else return;

        event.preventDefault();
        event.stopPropagation();
    }

    const closeButton = button('lb-close', '×', labels.close, close);

    root.append(image, closeButton, caption, counter);

    if (photos.length > 1) {
        root.append(
            button('lb-prev', '‹', labels.prev, () => show(index - 1)),
            button('lb-next', '›', labels.next, () => show(index + 1)),
        );
    }

    root.addEventListener('click', close);
    image.addEventListener('click', (event) => event.stopPropagation());
    root.addEventListener('touchstart', (event) => {
        touchX = event.changedTouches[0].clientX;
    }, { passive: true });
    root.addEventListener('touchend', (event) => {
        if (touchX === null || photos.length < 2) return;

        const delta = event.changedTouches[0].clientX - touchX;
        touchX = null;

        if (Math.abs(delta) > 48) {
            haptic(TAP);
            show(delta < 0 ? index + 1 : index - 1);
        }
    }, { passive: true });

    show(index);
    document.body.appendChild(root);
    document.addEventListener('keydown', onKey, true);
    closeButton.focus({ preventScroll: true });
    current = { close };
}
