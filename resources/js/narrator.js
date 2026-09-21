/**
 * The AI concierge introducing a scene: types the intro like speech, animates
 * the avatar and sound bars while "speaking", and can read it aloud with the
 * browser's speech synthesis when the guest asks (never automatically).
 * The full text is always in the DOM for screen readers and no-JS visitors.
 */
function initNarrators() {
    const narrators = [...document.querySelectorAll('[data-narrator]')];
    if (!narrators.length) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const canSpeak = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
    const played = new Set();
    const states = new Map();

    const fullText = (element) => element.querySelector('[data-narrator-text]').dataset.text;
    const isVisible = (element) => element.getClientRects().length > 0;

    function reveal(element, { finished = true } = {}) {
        const state = states.get(element);
        if (state?.timer) window.clearTimeout(state.timer);
        if (state) state.timer = null;

        element.querySelector('[data-narrator-text]').textContent = fullText(element);
        if (finished && !state?.speaking) element.classList.remove('is-speaking');
    }

    function stopSpeech(element) {
        const state = states.get(element);
        if (!state?.speaking) return;

        state.speaking = false;
        if (canSpeak) window.speechSynthesis.cancel();
        const button = element.querySelector('[data-narrator-listen]');
        button.textContent = element.dataset.listenLabel;
        if (!state.timer) element.classList.remove('is-speaking');
    }

    function halt(element) {
        stopSpeech(element);
        reveal(element);
    }

    function type(element) {
        const target = element.querySelector('[data-narrator-text]');
        const characters = [...fullText(element)];
        let index = 0;

        element.classList.add('is-speaking');
        target.textContent = '';

        const step = () => {
            index += 1;
            target.textContent = characters.slice(0, index).join('');

            const state = states.get(element);
            if (index >= characters.length) {
                state.timer = null;
                if (!state.speaking) element.classList.remove('is-speaking');
                return;
            }

            const pause = /[.!?。！？]/.test(characters[index - 1]) ? 220 : /[,、]/.test(characters[index - 1]) ? 110 : 22;
            state.timer = window.setTimeout(step, pause);
        };

        states.get(element).timer = window.setTimeout(step, 350);
    }

    function speak(element) {
        const state = states.get(element);
        const button = element.querySelector('[data-narrator-listen]');

        if (state.speaking) {
            stopSpeech(element);
            return;
        }

        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(fullText(element));
        utterance.lang = element.dataset.lang;
        utterance.onend = utterance.onerror = () => {
            if (state.speaking) stopSpeech(element);
        };

        state.speaking = true;
        button.textContent = element.dataset.stopLabel;
        element.classList.add('is-speaking');
        window.speechSynthesis.speak(utterance);
    }

    function refresh() {
        narrators.forEach((element) => {
            const visible = isVisible(element);
            const state = states.get(element);

            if (!visible) {
                if (state.active) {
                    halt(element);
                    state.active = false;
                }
                return;
            }

            if (state.active) return;
            state.active = true;

            const key = element.dataset.key;
            if (reducedMotion || played.has(key)) {
                reveal(element);
            } else {
                played.add(key);
                type(element);
            }
        });
    }

    narrators.forEach((element) => {
        states.set(element, { timer: null, speaking: false, active: false });

        const listen = element.querySelector('[data-narrator-listen]');
        listen.hidden = !canSpeak;
        listen.addEventListener('click', () => speak(element));
        element.querySelector('[data-narrator-skip]').addEventListener('click', () => halt(element));
    });

    window.addEventListener('hashchange', () => window.requestAnimationFrame(refresh));
    window.addEventListener('pagehide', () => { if (canSpeak) window.speechSynthesis.cancel(); });
    window.requestAnimationFrame(refresh);
}

document.addEventListener('DOMContentLoaded', initNarrators);
