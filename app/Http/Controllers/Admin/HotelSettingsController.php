<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentHotel;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveCustomDomain;
use App\Models\AuditLog;
use App\Services\ImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The owner's own screen for the hotel's profile, look, domain and publishing.
 */
class HotelSettingsController extends Controller
{
    use ResolvesCurrentHotel;

    public function __construct(private readonly ImageUploader $images) {}

    public function edit(Request $request): View
    {
        return view('admin.settings.edit', ['hotel' => $this->currentHotel($request)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $hotel = $this->currentHotel($request);
        $request->merge([
            'custom_domain' => strtolower(trim((string) $request->input('custom_domain'))),
            'currency' => strtoupper(trim((string) $request->input('currency'))),
        ]);
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9\s\-().]*$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
            'currency' => ['required', 'alpha', 'size:3'],
            'default_locale' => ['required', Rule::in(['id', 'en', 'ja'])],
            'check_in_time' => ['required', 'date_format:H:i'],
            'check_out_time' => ['required', 'date_format:H:i'],
            'public_status' => ['required', Rule::in(['draft', 'published'])],
            'custom_domain' => ['nullable', 'string', 'max:253', 'regex:/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', Rule::notIn([$appHost]), Rule::unique('hotels', 'custom_domain')->ignore($hotel->id)],
            'ai_instructions' => ['nullable', 'string', 'max:1500'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.ImageUploader::MAX_KILOBYTES],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.ImageUploader::MAX_KILOBYTES],
        ], ['custom_domain.regex' => 'Enter a domain such as hotel.example.com, without https:// or a path.', 'custom_domain.unique' => 'Another hotel already uses that domain.']);

        $previousDomain = $hotel->custom_domain;
        $domain = filled($data['custom_domain'] ?? null) ? strtolower($data['custom_domain']) : null;

        $changes = [
            ...collect($data)->except(['logo', 'cover', 'ai_instructions', 'custom_domain'])->all(),
            'custom_domain' => $domain,
            'check_in_time' => $data['check_in_time'],
            'check_out_time' => $data['check_out_time'],
            'settings' => [...($hotel->settings ?? []), 'ai_instructions' => filled($data['ai_instructions'] ?? null) ? trim($data['ai_instructions']) : null],
        ];

        foreach (['logo' => 'logo_path', 'cover' => 'cover_path'] as $field => $column) {
            if ($request->file($field)) {
                $this->images->delete($hotel->{$column});
                $changes[$column] = $this->images->store($request->file($field), 'hotels');
            } elseif ($request->boolean("remove_{$field}")) {
                $this->images->delete($hotel->{$column});
                $changes[$column] = null;
            }
        }

        $hotel->update($changes);

        foreach (array_filter([$previousDomain, $domain]) as $host) {
            Cache::forget(ResolveCustomDomain::cacheKey($host));
        }

        AuditLog::record($hotel->id, 'hotel.settings', 'Updated the hotel settings', $hotel, $request->user(), ['changed' => array_keys($hotel->getChanges())]);

        return redirect()->route('admin.settings.edit')->with('status', 'Hotel settings saved.');
    }
}
