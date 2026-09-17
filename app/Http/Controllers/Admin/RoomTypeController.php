<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentHotel;
use App\Http\Controllers\Controller;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoomTypeController extends Controller
{
    use ResolvesCurrentHotel;

    public function index(Request $request): View
    {
        $hotel = $this->currentHotel($request);

        $roomTypes = $hotel->roomTypes()->withCount('images')->orderBy('sort_order')->get();

        return view('admin.room-types.index', compact('hotel', 'roomTypes'));
    }

    public function create(Request $request): View
    {
        $hotel = $this->currentHotel($request);

        return view('admin.room-types.form', [
            'hotel' => $hotel,
            'roomType' => new RoomType(['max_adults' => 2, 'max_children' => 0, 'is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = $this->currentHotel($request);
        $data = $this->validated($request);

        $roomType = $hotel->roomTypes()->create($data);
        $this->syncImages($roomType, $request);

        return redirect()->route('admin.room-types.index')->with('status', "Room type \"{$roomType->name}\" created.");
    }

    public function edit(Request $request, RoomType $roomType): View
    {
        $hotel = $this->currentHotel($request);
        abort_if($roomType->hotel_id !== $hotel->id, 404);

        $roomType->load('images');

        return view('admin.room-types.form', compact('hotel', 'roomType'));
    }

    public function update(Request $request, RoomType $roomType): RedirectResponse
    {
        $hotel = $this->currentHotel($request);
        abort_if($roomType->hotel_id !== $hotel->id, 404);

        $roomType->update($this->validated($request, $roomType));
        $this->syncImages($roomType, $request);

        return redirect()->route('admin.room-types.index')->with('status', "Room type \"{$roomType->name}\" updated.");
    }

    public function destroy(Request $request, RoomType $roomType): RedirectResponse
    {
        $hotel = $this->currentHotel($request);
        abort_if($roomType->hotel_id !== $hotel->id, 404);

        $roomType->delete();

        return redirect()->route('admin.room-types.index')->with('status', 'Room type deleted.');
    }

    private function validated(Request $request, ?RoomType $roomType = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'translations.en.name' => ['nullable', 'string'],
            'translations.en.description' => ['nullable', 'string'],
            'translations.ja.name' => ['nullable', 'string'],
            'translations.ja.description' => ['nullable', 'string'],
            'size_sqm' => ['nullable', 'integer', 'min:1'],
            'max_adults' => ['required', 'integer', 'min:1'],
            'max_children' => ['required', 'integer', 'min:0'],
            'bed_type_1' => ['nullable', 'string', 'max:50'],
            'bed_count_1' => ['nullable', 'integer', 'min:1'],
            'bed_type_2' => ['nullable', 'string', 'max:50'],
            'bed_count_2' => ['nullable', 'integer', 'min:1'],
            'view_type' => ['nullable', 'string', 'max:50'],
            'breakfast_included' => ['sometimes', 'boolean'],
            'extra_bed_available' => ['sometimes', 'boolean'],
            'extra_bed_price' => ['nullable', 'numeric', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'amenities' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $bedConfig = [];
        if (! empty($data['bed_type_1'])) {
            $bedConfig[] = ['type' => $data['bed_type_1'], 'count' => (int) ($data['bed_count_1'] ?? 1)];
        }
        if (! empty($data['bed_type_2'])) {
            $bedConfig[] = ['type' => $data['bed_type_2'], 'count' => (int) ($data['bed_count_2'] ?? 1)];
        }

        $amenities = collect(explode(',', (string) ($data['amenities'] ?? '')))
            ->map(fn ($a) => Str::of($a)->trim()->slug('_')->toString())
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $data['name'],
            'slug' => $roomType?->slug ?? $this->uniqueSlug($request->user()->currentHotel(), $data['name']),
            'description' => $data['description'] ?? null,
            'translations' => [
                'en' => ['name' => $data['translations']['en']['name'] ?? null, 'description' => $data['translations']['en']['description'] ?? null],
                'ja' => ['name' => $data['translations']['ja']['name'] ?? null, 'description' => $data['translations']['ja']['description'] ?? null],
            ],
            'size_sqm' => $data['size_sqm'] ?? null,
            'max_adults' => $data['max_adults'],
            'max_children' => $data['max_children'],
            'bed_config' => $bedConfig,
            'view_type' => $data['view_type'] ?? null,
            'breakfast_included' => $request->boolean('breakfast_included'),
            'extra_bed_available' => $request->boolean('extra_bed_available'),
            'extra_bed_price' => $data['extra_bed_price'] ?? null,
            'base_price' => $data['base_price'],
            'amenities' => $amenities,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function uniqueSlug($hotel, string $name): string
    {
        $base = Str::slug($name) ?: 'room';
        $slug = $base;
        $suffix = 1;

        while ($hotel->roomTypes()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function syncImages(RoomType $roomType, Request $request): void
    {
        $deleteIds = collect($request->input('delete_images', []))->map(fn ($id) => (int) $id);
        if ($deleteIds->isNotEmpty()) {
            $roomType->images()->whereIn('id', $deleteIds)->delete();
        }

        $newImages = collect($request->input('new_images', []))
            ->filter(fn ($row) => filled($row['url'] ?? null));

        $sort = $roomType->images()->max('sort_order') + 1;
        foreach ($newImages as $row) {
            $roomType->images()->create([
                'image_url' => $row['url'],
                'alt_text' => $row['alt'] ?? $roomType->name,
                'tags' => collect(explode(',', $row['tags'] ?? ''))->map(fn ($t) => trim($t))->filter()->values()->all(),
                'sort_order' => $sort++,
            ]);
        }
    }
}
