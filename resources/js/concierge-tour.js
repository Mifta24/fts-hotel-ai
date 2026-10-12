/**
 * The guided tour: the concierge walks the guest from scene to scene — the
 * rooms, a couple of room pages, then the facilities — while each scene's
 * narrator says its line. It moves on once the line has been said and the guest
 * has had a moment to take it in, and the guest can skip ahead or stop at any
 * time. The progress lives in sessionStorage because every stop is its own page.
 */
const DWELL_BASE_MS = 1800;
const DWELL_PER_CHARACTER_MS = 22;
const DWELL_MAX_MS = 5200;
const NO_NARRATOR_WAIT_MS = 5000;

/**
 * @param {object} options
 * @param {Array<{key: string, url: string, line: string, spot: ?string}>} options.stops
 * @param {string} options.storageKey
 * @param {{progress: string, next: string, stop: string}} options.labels
 * @param {Element} options.stage
 * @param {() => void} options.onFinish      the tour ran to its end
 */
export function createTour({ stops, storageKey, labels, stage, onFinish }) {
    let bar = null;
    let timer = null;

    const read = () => {
        try {
            const state = JSON.parse(sessionStorage.getItem(storageKey) || 'null');

            return Number.isInteger(state?.step) ? state.step : null;
        } catch {
            return null;
        }
    };

    const write = (step) => {
        try {
            if (step === null) sessionStorage.removeItem(storageKey);
            else sessionStorage.setItem(storageKey, JSON.stringify({ step }));
        } catch { /* private mode: the tour just cannot carry on to the next scene */ }
    };

    const here = (stop) => new URL(stop.url, window.location.href).pathname === window.location.pathname;

    function clear() {
        window.clearTimeout(timer);
        timer = null;
        bar?.remove();
        bar = null;
        stage.removeAttribute('data-tour-step');
        stage.querySelectorAll('.tour-spot').forEach((el) => el.classList.remove('tour-spot'));
    }

    function stop() {
        write(null);
        clear();
    }

    function finish() {
        stop();
        onFinish();
    }

    function goTo(step) {
        if (step >= stops.length) {
            finish();

            return;
        }

        write(step);
        clear();

        const target = stops[step];
        if (here(target)) {
            begin(step);
        } else if (window.hotelStage) {
            window.hotelStage.leave(target.url, target.line);
        } else {
            window.location.href = target.url;
        }
    }

    function showBar(step) {
        bar = document.createElement('div');
        bar.className = 'tour-bar';
        bar.setAttribute('role', 'status');

        const dots = document.createElement('span');
        dots.className = 'tour-dots';
        stops.forEach((_, index) => {
            const dot = document.createElement('i');
            if (index <= step) dot.className = 'is-done';
            dots.appendChild(dot);
        });

        const progress = document.createElement('span');
        progress.className = 'tour-progress';
        progress.textContent = labels.progress.replace(':current', step + 1).replace(':total', stops.length);

        const next = document.createElement('button');
        next.type = 'button';
        next.textContent = labels.next;
        next.addEventListener('click', () => goTo(step + 1));

        const end = document.createElement('button');
        end.type = 'button';
        end.className = 'is-quiet';
        end.textContent = labels.stop;
        end.addEventListener('click', stop);

        bar.append(dots, progress, next, end);
        stage.appendChild(bar);
    }

    /** A scene of the tour is up: draw the eye to it, then move on once its line is said. */
    function begin(step) {
        const target = stops[step];
        stage.setAttribute('data-tour-step', String(step));
        showBar(step);

        if (target.spot) stage.querySelector(target.spot)?.classList.add('tour-spot');

        const narrator = [...document.querySelectorAll('[data-narrator]')].find((el) => el.dataset.key === target.key);
        const length = narrator?.querySelector('[data-narrator-text]')?.dataset.text?.length ?? 0;
        const dwell = Math.min(DWELL_BASE_MS + length * DWELL_PER_CHARACTER_MS, DWELL_MAX_MS);
        const advance = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => goTo(step + 1), dwell);
        };

        const alreadySaid = narrator?.querySelector('.narrator-typed')?.textContent && narrator.querySelector('.narrator-rest')?.textContent === '';

        // A fallback runs either way, in case the narrator never reports back.
        timer = window.setTimeout(advance, narrator ? NO_NARRATOR_WAIT_MS + length * 40 : NO_NARRATOR_WAIT_MS);

        if (alreadySaid) advance();
        else narrator?.addEventListener('narrator:done', advance, { once: true });
    }

    return {
        start() {
            goTo(0);
        },

        /** On every page load: carry on if the guest is in the middle of a tour. */
        resume() {
            const step = read();
            if (step === null) return;

            if (stops[step] && here(stops[step])) begin(step);
            else write(null);
        },

        stop,
        isRunning: () => read() !== null,
    };
}
