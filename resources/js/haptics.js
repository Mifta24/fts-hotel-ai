/**
 * A light tap under the guest's finger on phones that can vibrate. Silent
 * everywhere else; the browser decides whether it is allowed.
 */
export const TAP = 8;
export const PRESS = 14;
export const DONE = [14, 40, 18];

export function haptic(pattern = TAP) {
    try {
        navigator.vibrate?.(pattern);
    } catch { /* not allowed here: nothing to do */ }
}
