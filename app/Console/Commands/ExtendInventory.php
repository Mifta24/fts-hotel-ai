<?php

namespace App\Console\Commands;

use App\Models\RoomType;
use App\Services\Reservation\InventoryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:extend {--days=365 : How many days ahead rooms should be open for sale}')]
#[Description('Open further nights for every room type, repeating the rooms and prices of the week before')]
class ExtendInventory extends Command
{
    public function handle(InventoryService $inventory): int
    {
        $days = max(1, (int) $this->option('days'));
        $added = 0;

        RoomType::where('is_active', true)->each(function (RoomType $roomType) use ($inventory, $days, &$added): void {
            $added += $inventory->extend($roomType, $days);
        });

        $this->info("Opened {$added} night(s).");

        return self::SUCCESS;
    }
}
