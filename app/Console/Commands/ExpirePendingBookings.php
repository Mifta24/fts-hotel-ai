<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Reservation\ReservationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('bookings:expire-pending {--hours=24 : Cancel pending requests older than this many hours}')]
#[Description('Cancel reservation requests the hotel never confirmed and give their rooms back to the inventory')]
class ExpirePendingBookings extends Command
{
    public function handle(ReservationService $reservations): int
    {
        $cutoff = now()->subHours(max(1, (int) $this->option('hours')));
        $expired = 0;

        Booking::where('status', Booking::STATUS_PENDING)
            ->where('created_at', '<=', $cutoff)
            ->pluck('id')
            ->each(function (int $id) use ($reservations, &$expired): void {
                DB::transaction(function () use ($id, $reservations, &$expired): void {
                    $booking = Booking::whereKey($id)->lockForUpdate()->first();

                    if (! $booking || $booking->status !== Booking::STATUS_PENDING) {
                        return;
                    }

                    $reservations->releaseInventory($booking);
                    $booking->update(['status' => Booking::STATUS_CANCELLED]);
                    $expired++;
                });
            });

        $this->info("Expired {$expired} pending booking(s).");

        return self::SUCCESS;
    }
}
