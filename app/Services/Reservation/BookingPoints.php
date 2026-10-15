<?php

namespace App\Services\Reservation;

use App\Core\Member\Models\MemberPoint;
use App\Core\Member\Services\PointsLedger;
use App\Models\Booking;
use App\Models\Hotel;

/**
 * The hotel's side of member points: a stay earns points once it is over,
 * and a stay that is cancelled afterwards gives them back.
 */
class BookingPoints
{
    public function __construct(private readonly PointsLedger $ledger) {}

    /**
     * Pays out completed stays and takes back points from stays that
     * were cancelled after paying out. Safe to run any number of times.
     *
     * @return array{earned: int, reversed: int} how many stays were paid out and taken back
     */
    public function settle(Hotel $hotel): array
    {
        $program = $hotel->loyaltyProgram();

        if (! $program) {
            return ['earned' => 0, 'reversed' => 0];
        }

        $earned = 0;
        $reversed = 0;
        $morph = (new Booking)->getMorphClass();

        Booking::where('hotel_id', $hotel->id)
            ->whereNotNull('member_id')
            ->where('status', Booking::STATUS_COMPLETED)
            ->whereNotExists(fn ($query) => $query->from('member_points')
                ->whereColumn('member_points.source_id', 'bookings.id')
                ->where('member_points.source_type', $morph)
                ->where('member_points.type', MemberPoint::TYPE_EARN))
            ->with('member')
            ->lazyById(100)
            ->each(function (Booking $booking) use ($program, &$earned): void {
                $points = $program->pointsFor((float) $booking->total_price);

                if ($points > 0 && $booking->member && $this->ledger->earn($booking->member, $points, $booking, "Stay {$booking->reference}")) {
                    $earned++;
                }
            });

        Booking::where('hotel_id', $hotel->id)
            ->whereNotNull('member_id')
            ->where('status', Booking::STATUS_CANCELLED)
            ->whereExists(fn ($query) => $query->from('member_points')
                ->whereColumn('member_points.source_id', 'bookings.id')
                ->where('member_points.source_type', $morph)
                ->where('member_points.type', MemberPoint::TYPE_EARN))
            ->with('member')
            ->lazyById(100)
            ->each(function (Booking $booking) use (&$reversed): void {
                if ($booking->member && $this->ledger->reverse($booking->member, $booking, "Stay {$booking->reference} was cancelled")) {
                    $reversed++;
                }
            });

        return ['earned' => $earned, 'reversed' => $reversed];
    }
}
