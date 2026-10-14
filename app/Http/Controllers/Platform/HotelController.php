<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Services\HotelOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HotelController extends Controller
{
    public function __construct(private readonly HotelOnboarding $onboarding) {}

    public function index(): View
    {
        $hotels = Hotel::withCount(['roomTypes', 'bookings'])->with('users')->orderBy('name')->get();

        return view('platform.hotels.index', compact('hotels'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(HotelOnboarding::RESERVED_SLUGS), Rule::unique('hotels', 'slug')],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'default_locale' => ['required', Rule::in(['id', 'en', 'ja'])],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->onboarding->create($data, $data['owner_email'], $data['owner_name'] ?? null);

        $message = "Hotel \"{$result['hotel']->name}\" created at /{$result['hotel']->slug}. It stays hidden until it is published.";

        if ($result['password']) {
            $message .= " A new owner account was created for {$result['owner']->email} — temporary password: {$result['password']} (shown only once).";
        }

        return redirect()->route('platform.hotels.index')->with('status', $message);
    }

    public function updateAiModel(Request $request, Hotel $hotel): RedirectResponse
    {
        $data = $request->validate(['ai_model' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:\/-]+$/']]);

        $hotel->update(['settings' => [...($hotel->settings ?? []), 'ai_model' => filled($data['ai_model'] ?? null) ? $data['ai_model'] : null]]);
        AuditLog::record($hotel->id, 'hotel.ai_model', 'Changed the AI model of the concierge', $hotel, $request->user(), ['ai_model' => $data['ai_model'] ?? null]);

        return back()->with('status', "{$hotel->name} now uses ".($hotel->setting('ai_model') ?? 'the default AI model').'.');
    }

    public function updateFeature(Request $request, Hotel $hotel): RedirectResponse
    {
        $data = $request->validate([
            'feature' => ['required', Rule::in(Hotel::FEATURES)],
            'enabled' => ['required', 'boolean'],
        ]);

        $features = collect($hotel->features ?? [])->reject(fn (string $feature) => $feature === $data['feature']);

        if ($request->boolean('enabled')) {
            $features->push($data['feature']);
        }

        $hotel->update(['features' => $features->unique()->values()->all()]);

        $state = $request->boolean('enabled') ? 'on' : 'off';
        AuditLog::record($hotel->id, 'hotel.feature', "Switched the {$data['feature']} add-on {$state}", $hotel, $request->user(), ['feature' => $data['feature'], 'enabled' => $request->boolean('enabled')]);

        return back()->with('status', "The {$data['feature']} add-on is now {$state} for {$hotel->name}.");
    }

    public function updateStatus(Request $request, Hotel $hotel): RedirectResponse
    {
        $data = $request->validate(['public_status' => ['required', Rule::in(['draft', 'published'])]]);

        $hotel->update(['public_status' => $data['public_status']]);
        AuditLog::record($hotel->id, 'hotel.status', "Hotel is now {$data['public_status']}", $hotel, $request->user());

        return back()->with('status', "{$hotel->name} is now {$data['public_status']}.");
    }
}
