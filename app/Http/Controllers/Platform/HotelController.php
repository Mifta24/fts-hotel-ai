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

    public function updateStatus(Request $request, Hotel $hotel): RedirectResponse
    {
        $data = $request->validate(['public_status' => ['required', Rule::in(['draft', 'published'])]]);

        $hotel->update(['public_status' => $data['public_status']]);
        AuditLog::record($hotel->id, 'hotel.status', "Hotel is now {$data['public_status']}", $hotel, $request->user());

        return back()->with('status', "{$hotel->name} is now {$data['public_status']}.");
    }
}
