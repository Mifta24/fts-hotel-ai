<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentHotel;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RoomInventory;
use App\Services\Reservation\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    use ResolvesCurrentHotel;

    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $hotel = $this->currentHotel($request);

        $roomTypes = $hotel->roomTypes()->orderBy('sort_order')->get();
        $roomType = $roomTypes->firstWhere('id', (int) $request->query('room')) ?? $roomTypes->first();

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? CarbonImmutable::createFromFormat('!Y-m', $request->query('month'))
            : CarbonImmutable::today()->startOfMonth();

        $nights = $roomType
            ? RoomInventory::where('room_type_id', $roomType->id)
                ->whereBetween('stay_date', [$month->startOfDay()->toDateTimeString(), $month->endOfMonth()->startOfDay()->toDateTimeString()])
                ->orderBy('stay_date')
                ->get()
                ->keyBy(fn (RoomInventory $night) => $night->stay_date->toDateString())
            : collect();

        return view('admin.inventory.index', compact('hotel', 'roomTypes', 'roomType', 'month', 'nights'));
    }

    public function update(Request $request): RedirectResponse
    {
        $hotel = $this->currentHotel($request);

        $data = $request->validate([
            'room_type_id' => ['required', 'integer'],
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now($hotel->timezone)->toDateString()],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'total_units' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6'],
        ]);

        $roomType = $hotel->roomTypes()->findOrFail($data['room_type_id']);

        $written = $this->inventory->apply(
            $roomType,
            CarbonImmutable::parse($data['from']),
            CarbonImmutable::parse($data['to']),
            isset($data['total_units']) ? (int) $data['total_units'] : null,
            isset($data['price']) ? (float) $data['price'] : null,
            isset($data['weekdays']) ? array_map('intval', $data['weekdays']) : null,
        );

        AuditLog::record($hotel->id, 'inventory.updated', "Changed {$written} night(s) of {$roomType->name} ({$data['from']} to {$data['to']})", $roomType, $request->user(), array_filter(['total_units' => $data['total_units'] ?? null, 'price' => $data['price'] ?? null], fn ($v) => $v !== null));

        return redirect()
            ->route('admin.inventory.index', ['room' => $roomType->id, 'month' => substr($data['from'], 0, 7)])
            ->with('status', "Updated {$written} night(s) of {$roomType->name}.");
    }
}
