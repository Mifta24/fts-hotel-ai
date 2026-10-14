<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentHotel;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * How the hotel's members earn points and what each tier gives them.
 */
class LoyaltyController extends Controller
{
    use ResolvesCurrentHotel;

    private const TIER_ROWS = 4;

    public function edit(Request $request): View
    {
        $hotel = $this->currentHotel($request);
        abort_unless($hotel->supportsLoyalty(), 404);

        return view('admin.loyalty.edit', ['hotel' => $hotel, 'program' => $hotel->loyaltySettings(), 'rows' => self::TIER_ROWS]);
    }

    public function update(Request $request): RedirectResponse
    {
        $hotel = $this->currentHotel($request);
        abort_unless($hotel->supportsLoyalty(), 404);

        $data = $request->validate([
            'spend_per_point' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'tiers' => ['required', 'array', 'max:'.self::TIER_ROWS],
            'tiers.*.name' => ['nullable', 'string', 'max:40'],
            'tiers.*.min_points' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'tiers.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $tiers = collect($data['tiers'])
            ->filter(fn (array $tier) => filled($tier['name'] ?? null))
            ->map(fn (array $tier) => [
                'name' => trim($tier['name']),
                'min_points' => (int) ($tier['min_points'] ?? 0),
                'discount_percent' => (float) ($tier['discount_percent'] ?? 0),
            ])
            ->sortBy('min_points')
            ->values();

        if ($tiers->isEmpty()) {
            throw ValidationException::withMessages(['tiers' => 'Add at least one tier.']);
        }

        if ($tiers->pluck('min_points')->unique()->count() !== $tiers->count()) {
            throw ValidationException::withMessages(['tiers' => 'Two tiers cannot start at the same number of points.']);
        }

        $hotel->update(['settings' => [...($hotel->settings ?? []), 'loyalty' => ['spend_per_point' => (float) $data['spend_per_point'], 'tiers' => $tiers->all()]]]);
        AuditLog::record($hotel->id, 'loyalty.updated', 'Changed the member points program', $hotel, $request->user());

        return back()->with('status', 'The points program was saved. Bookings already made keep their discount.');
    }
}
