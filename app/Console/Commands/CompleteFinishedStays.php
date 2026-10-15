<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Hotel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:complete-stays')]
#[Description('Mark confirmed reservations as completed once their check-out day has come')]
class CompleteFinishedStays extends Command
{
    public function handle(): int
    {
        $completed = 0;

        Hotel::query()->each(function (Hotel $hotel) use (&$completed): void {
            Booking::where('hotel_id', $hotel->id)
                ->where('status', Booking::STATUS_CONFIRMED)
                ->whereDate('check_out', '<=', now($hotel->timezone)->toDateString())
                ->lazyById(100)
                ->each(function (Booking $booking) use ($hotel, &$completed): void {
                    $booking->update(['status' => Booking::STATUS_COMPLETED]);
                    AuditLog::record($hotel->id, 'booking.completed', 'The stay is over, so the booking was marked completed', $booking, null, [], AuditLog::ACTOR_SYSTEM);
                    $completed++;
                });
        });

        $this->info("Completed {$completed} finished stay(s).");

        return self::SUCCESS;
    }
}
