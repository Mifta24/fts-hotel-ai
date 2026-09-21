/**
 * Full-screen stage chrome shared by the opening screen and the lobby:
 * hides the "preparing" loader once the hero image is ready, and fades the
 * scene out before following an "enter" link so moving between screens feels
 * like walking through the lobby rather than loading a page.
 */
function initStage() {
    const stage = document.querySelector('.stage');
    if (!stage) return;

    const loader = stage.querySelector('[data-stage-loader]');
    const image = stage.querySelector('.stage-image');
    const showScene = () => loader?.classList.add('is-ready');

    if (!image || image.complete) {
        showScene();
    } else {
        image.addEventListener('load', showScene, { once: true });
        image.addEventListener('error', showScene, { once: true });
    }

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            stage.classList.remove('is-leaving');
            tour?.classList.remove('is-visible');
        }
    });

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const tour = stage.querySelector('[data-stage-tour]');

    /** Fade the stage out, then walk the guest over to `href`. */
    function leave(href, line = null) {
        // The concierge says where she is taking the guest, then the scene
        // walks forward into the next one.
        if (line && tour) {
            tour.textContent = line;
            tour.classList.add('is-visible');
        }

        stage.classList.add('is-leaving');

        const chime = window.hotelSound?.isEnabled() ?? false;
        if (chime) window.hotelSound.play('enter');

        const hold = line ? 1100 : chime ? 700 : reducedMotion ? 0 : 380;
        window.setTimeout(() => { window.location.href = href; }, hold);
    }

    document.querySelectorAll('[data-stage-exit]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const modified = event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
            if (event.defaultPrevented || modified || link.target === '_blank') return;

            event.preventDefault();
            leave(link.href, link.dataset.tourLine);
        });
    });

    window.hotelStage = { leave };
}

document.addEventListener('DOMContentLoaded', initStage);
