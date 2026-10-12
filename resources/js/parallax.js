/**
 * Depth for the stage: the backdrop and the concierge cut-out drift a little
 * against the guest's pointer (or the tilt of a phone), the nearer one further,
 * so the scene reads as layers rather than a flat picture. The drift is eased
 * and only runs while it is still catching up. Skipped for guests who ask for
 * less motion.
 */
function initParallax() {
    const stage = document.querySelector('.stage');
    if (!stage || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const layers = [...stage.querySelectorAll('.stage-image, .stage-character')];
    if (!layers.length) return;

    const EASE = 0.08;
    const SETTLED = 0.002;
    let targetX = 0;
    let targetY = 0;
    let x = 0;
    let y = 0;
    let frame = null;

    const clamp = (value) => Math.max(-1, Math.min(1, value));

    function tick() {
        x += (targetX - x) * EASE;
        y += (targetY - y) * EASE;

        layers.forEach((layer) => {
            layer.style.setProperty('--parallax-x', x.toFixed(3));
            layer.style.setProperty('--parallax-y', y.toFixed(3));
        });

        frame = Math.abs(targetX - x) > SETTLED || Math.abs(targetY - y) > SETTLED ? requestAnimationFrame(tick) : null;
    }

    function aim(nextX, nextY) {
        targetX = clamp(nextX);
        targetY = clamp(nextY);
        if (!frame) frame = requestAnimationFrame(tick);
    }

    window.addEventListener('pointermove', (event) => {
        if (event.pointerType !== 'mouse') return;

        aim((event.clientX / window.innerWidth - 0.5) * 2, (event.clientY / window.innerHeight - 0.5) * 2);
    }, { passive: true });

    document.documentElement.addEventListener('pointerleave', () => aim(0, 0));

    // A tilted phone does the same. iOS asks for permission first, which is not worth a prompt, so it is left out there.
    if ('DeviceOrientationEvent' in window && typeof DeviceOrientationEvent.requestPermission !== 'function') {
        window.addEventListener('deviceorientation', (event) => {
            if (event.gamma == null || event.beta == null) return;

            aim(event.gamma / 25, (event.beta - 45) / 25);
        }, { passive: true });
    }
}

document.addEventListener('DOMContentLoaded', initParallax);
