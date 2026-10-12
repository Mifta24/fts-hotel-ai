/**
 * The small celebration when a reservation request goes through: a burst of
 * confetti, a calendar file to keep the dates, and a way to share the stay.
 */
const COLOURS = ['#b48c42', '#e9c987', '#2f6b4a', '#fffdf8', '#9ec7a8'];

/** A short, soft burst from the middle of the stage. Skipped for guests who ask for less motion. */
export function confetti() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const canvas = document.createElement('canvas');
    canvas.className = 'confetti';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    document.body.appendChild(canvas);

    const context = canvas.getContext('2d');
    const pieces = Array.from({ length: 90 }, () => ({
        x: canvas.width / 2 + (Math.random() - 0.5) * 120,
        y: canvas.height * 0.62,
        vx: (Math.random() - 0.5) * 11,
        vy: -(Math.random() * 11 + 6),
        size: Math.random() * 6 + 4,
        spin: Math.random() * Math.PI,
        turn: (Math.random() - 0.5) * 0.3,
        colour: COLOURS[Math.floor(Math.random() * COLOURS.length)],
    }));
    const started = performance.now();

    function frame(now) {
        const elapsed = now - started;
        context.clearRect(0, 0, canvas.width, canvas.height);

        pieces.forEach((piece) => {
            piece.vy += 0.32;
            piece.vx *= 0.99;
            piece.x += piece.vx;
            piece.y += piece.vy;
            piece.spin += piece.turn;

            context.save();
            context.globalAlpha = Math.max(0, 1 - elapsed / 2200);
            context.translate(piece.x, piece.y);
            context.rotate(piece.spin);
            context.fillStyle = piece.colour;
            context.fillRect(-piece.size / 2, -piece.size / 4, piece.size, piece.size / 2);
            context.restore();
        });

        if (elapsed < 2200) requestAnimationFrame(frame);
        else canvas.remove();
    }

    requestAnimationFrame(frame);
}

const icsEscape = (value) => String(value).replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\n/g, '\\n');
const icsDate = (iso) => iso.replaceAll('-', '');

/**
 * The stay as an all-day calendar event (check-out is the first day it no
 * longer covers, which is how all-day events end).
 *
 * @param {{booking_reference: string, name: string, check_in: string, check_out: string}} booking
 */
export function calendarFile(booking, hotelName) {
    const stamp = new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d+/, '');
    const lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//FTS Hotel AI//Reservation//EN',
        'CALSCALE:GREGORIAN',
        'BEGIN:VEVENT',
        `UID:${booking.booking_reference}@fts-hotel-ai`,
        `DTSTAMP:${stamp}`,
        `DTSTART;VALUE=DATE:${icsDate(booking.check_in)}`,
        `DTEND;VALUE=DATE:${icsDate(booking.check_out)}`,
        `SUMMARY:${icsEscape(`${hotelName} · ${booking.name}`)}`,
        `DESCRIPTION:${icsEscape(`${booking.booking_reference}`)}`,
        'END:VEVENT',
        'END:VCALENDAR',
    ];

    return new Blob([`${lines.join('\r\n')}\r\n`], { type: 'text/calendar;charset=utf-8' });
}
