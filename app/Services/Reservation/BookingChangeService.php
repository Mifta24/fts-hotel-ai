<?php

namespace App\Services\Reservation;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Hotel;
use App\Notifications\GuestChangedBooking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What a guest may do to a reservation they made: find it again with its
 * reference and the contact they gave, move it to other dates, or cancel it.
 * Every change re-checks availability and tells the hotel.
 */
class BookingChangeService
{
    public function __construct(private readonly ReservationService $reservations) {}

    /**
     * The booking only if the contact matches the one left when booking, so a
     * reference alone (printed, forwarded, guessed) is never enough.
     */
    public function findForGuest(Hotel $hotel, string $reference, string $contact): ?Booking
    {
        $booking = Booking::where('hotel_id', $hotel->id)
            ->where('reference', strtoupper(trim($reference)))
            ->with('roomType')
            ->first();

        return $booking && $this->contactMatches($booking, $contact) ? $booking : null;
    }

    /**
     * Whether a guest can still change this booking: it must be live and not yet started.
     */
    public function isChangeable(Booking $booking, Hotel $hotel): bool
    {
        return in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED], true)
            && $booking->check_in->toDateString() >= now($hotel->timezone)->toDateString();
    }

    /**
     * Moves the stay to new dates, keeping room, rooms and party. A confirmed
     * booking goes back to pending, because the hotel agreed to other nights.
     * Returns null when the new nights are not available (nothing changes).
     */
    public function reschedule(Booking $booking, Hotel $hotel, CarbonImmutable $checkIn, CarbonImmutable $checkOut): ?Booking
    {
        try {
            $moved = $this->move($booking, $hotel, $checkIn, $checkOut);
        } catch (\DomainException) {
            return null;
        }

        if ($moved) {
            $this->reservations->notifyStaff($hotel, new GuestChangedBooking($moved, 'rescheduled'));
        }

        return $moved;
    }

    /**
     * @throws \DomainException when the new nights are not available; the transaction is then undone
     */
    private function move(Booking $booking, Hotel $hotel, CarbonImmutable $checkIn, CarbonImmutable $checkOut): ?Booking
    {
        return DB::transaction(function () use ($booking, $hotel, $checkIn, $checkOut): ?Booking {
            $current = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $this->isChangeable($current, $hotel)) {
                return null;
            }

            $this->reservations->releaseInventory($current);

            $quote = $this->reservations->quote($current->roomType, $checkIn, $checkOut, $current->room_count, $current->extra_bed, lockForUpdate: true, discountPercent: (float) $current->discount_percent);

            if (! $quote) {
                throw new \DomainException('Not available');
            }

            $from = $current->check_in->toDateString().' → '.$current->check_out->toDateString();

            $current->update([
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'total_price' => $quote['grand_total'],
                'discount_amount' => $quote['discount_amount'],
                'status' => Booking::STATUS_PENDING,
            ]);
            $this->reservations->holdInventory($current);

            AuditLog::record($hotel->id, 'booking.rescheduled', "Guest moved the stay from {$from} to {$checkIn->toDateString()} → {$checkOut->toDateString()}", $current, null, ['from' => $from], AuditLog::ACTOR_GUEST);

            return $current;
        });
    }

    public function cancel(Booking $booking, Hotel $hotel): bool
    {
        $cancelled = DB::transaction(function () use ($booking, $hotel): ?Booking {
            $current = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $this->isChangeable($current, $hotel)) {
                return null;
            }

            $this->reservations->releaseInventory($current);
            $current->update(['status' => Booking::STATUS_CANCELLED]);

            AuditLog::record($hotel->id, 'booking.cancelled', 'Guest cancelled the booking', $current, null, [], AuditLog::ACTOR_GUEST);

            return $current;
        });

        if ($cancelled) {
            $this->reservations->notifyStaff($hotel, new GuestChangedBooking($cancelled, 'cancelled'));
        }

        return $cancelled !== null;
    }

    private function contactMatches(Booking $booking, string $contact): bool
    {
        $contact = trim($contact);

        if (str_contains($contact, '@')) {
            return $booking->guest_email !== null && strcasecmp($booking->guest_email, $contact) === 0;
        }

        $given = preg_replace('/\D+/', '', $contact);
        $stored = preg_replace('/\D+/', '', (string) $booking->guest_phone);

        // Compare the last nine digits so +62 811… and 0811… are the same number.
        return strlen($given) >= 6 && strlen($stored) >= 6 && substr($given, -9) === substr($stored, -9);
    }
}
