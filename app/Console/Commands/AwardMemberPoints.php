<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Services\Reservation\BookingPoints;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('members:award-points')]
#[Description('Give members the points their finished stays earned, and take back those of cancelled ones')]
class AwardMemberPoints extends Command
{
    public function handle(BookingPoints $points): int
    {
        Hotel::whereJsonContains('features', Hotel::FEATURE_MEMBER_PLUS)->each(function (Hotel $hotel) use ($points): void {
            $result = $points->settle($hotel);

            $this->line("{$hotel->name}: {$result['earned']} stays paid out, {$result['reversed']} taken back.");
        });

        return self::SUCCESS;
    }
}
