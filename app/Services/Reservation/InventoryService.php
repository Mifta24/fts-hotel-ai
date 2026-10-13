<?php

namespace App\Services\Reservation;

use App\Models\RoomInventory;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lets the hotel decide how many rooms it sells and at what price per night.
 * Everything the concierge and the reservation form quote comes from these rows.
 */
class InventoryService
{
    public const MAX_RANGE_DAYS = 366;

    /**
     * Sets the number of rooms for sale and/or the nightly price on every night
     * of the range (optionally only on some weekdays). Nights that do not exist
     * yet are created, which needs both values. Rooms already booked are never
     * taken away.
     *
     * @param  list<int>|null  $weekdays  0 (Sunday) to 6 (Saturday); null means every day
     * @return int how many nights were written
     *
     * @throws ValidationException
     */
    public function apply(RoomType $roomType, CarbonImmutable $from, CarbonImmutable $to, ?int $totalUnits, ?float $price, ?array $weekdays = null): int
    {
        if ($totalUnits === null && $price === null) {
            throw ValidationException::withMessages(['total_units' => 'Enter the rooms for sale, the price, or both.']);
        }

        if ($from->diffInDays($to) >= self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => 'A range can cover at most '.self::MAX_RANGE_DAYS.' nights.']);
        }

        $dates = collect();
        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            if ($weekdays === null || in_array($date->dayOfWeek, $weekdays, true)) {
                $dates->push($date);
            }
        }

        if ($dates->isEmpty()) {
            throw ValidationException::withMessages(['weekdays' => 'None of the selected weekdays falls inside that range.']);
        }

        return DB::transaction(function () use ($roomType, $dates, $totalUnits, $price): int {
            $existing = RoomInventory::where('room_type_id', $roomType->id)
                ->whereIn('stay_date', $dates->map(fn (CarbonImmutable $date) => $date->startOfDay()->toDateTimeString())->all())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (RoomInventory $night) => $night->stay_date->toDateString());

            $missing = $dates->reject(fn (CarbonImmutable $date) => $existing->has($date->toDateString()));

            if ($missing->isNotEmpty() && ($totalUnits === null || $price === null)) {
                throw ValidationException::withMessages(['price' => 'Some of those nights have no availability yet, so both the rooms for sale and the price are needed (first: '.$missing->first()->toDateString().').']);
            }

            if ($totalUnits !== null) {
                $tooLow = $existing->filter(fn (RoomInventory $night) => $night->booked_units > $totalUnits);

                if ($tooLow->isNotEmpty()) {
                    $list = $tooLow->take(3)->map(fn (RoomInventory $night) => $night->stay_date->toDateString().' ('.$night->booked_units.' booked)')->implode(', ');

                    throw ValidationException::withMessages(['total_units' => "Rooms for sale cannot be lower than the rooms already booked: {$list}."]);
                }
            }

            foreach ($dates as $date) {
                $night = $existing->get($date->toDateString()) ?? new RoomInventory([
                    'room_type_id' => $roomType->id,
                    'stay_date' => $date->toDateString(),
                    'booked_units' => 0,
                ]);

                $night->fill(array_filter(['total_units' => $totalUnits, 'price' => $price], fn ($value) => $value !== null))->save();
            }

            return $dates->count();
        });
    }

    /**
     * Opens nights up to $days ahead for every room type that already sells,
     * repeating the rooms for sale and the price of the same weekday a week
     * earlier. Returns how many nights were added.
     */
    public function extend(RoomType $roomType, int $days): int
    {
        $last = RoomInventory::where('room_type_id', $roomType->id)->orderByDesc('stay_date')->first();

        if (! $last) {
            return 0;
        }

        $horizon = CarbonImmutable::today()->addDays($days);
        $prices = RoomInventory::where('room_type_id', $roomType->id)
            ->whereDate('stay_date', '>', $last->stay_date->subDays(7)->toDateString())
            ->pluck('price', 'stay_date')
            ->mapWithKeys(fn ($price, $date) => [CarbonImmutable::parse($date)->toDateString() => (float) $price])
            ->all();

        $rows = [];
        for ($date = CarbonImmutable::parse($last->stay_date)->addDay(); $date->lte($horizon); $date = $date->addDay()) {
            $price = $prices[$date->subDays(7)->toDateString()] ?? (float) $last->price;
            $prices[$date->toDateString()] = $price;

            $rows[] = [
                'room_type_id' => $roomType->id,
                'stay_date' => $date->startOfDay()->toDateTimeString(),
                'total_units' => $last->total_units,
                'booked_units' => 0,
                'price' => $price,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            RoomInventory::insertOrIgnore($chunk);
        }

        return count($rows);
    }
}
