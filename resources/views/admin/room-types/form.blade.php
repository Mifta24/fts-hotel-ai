@php
    $isEdit = $roomType->exists;
    $bed1 = $roomType->bed_config[0] ?? null;
    $bed2 = $roomType->bed_config[1] ?? null;
@endphp

<x-admin-layout :title="$isEdit ? 'Edit room type' : 'Add room type'">
    <form method="POST" action="{{ $isEdit ? route('admin.room-types.update', $roomType) : route('admin.room-types.store') }}" class="space-y-8">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Basics</h2>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-stone-700">Name (Indonesian, default)</label>
                    <input type="text" name="name" value="{{ old('name', $roomType->name) }}" required
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-stone-700">Description (Indonesian, default)</label>
                    <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('description', $roomType->description) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Name (English)</label>
                    <input type="text" name="translations[en][name]" value="{{ old('translations.en.name', $roomType->translations['en']['name'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Name (Japanese)</label>
                    <input type="text" name="translations[ja][name]" value="{{ old('translations.ja.name', $roomType->translations['ja']['name'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Description (English)</label>
                    <textarea name="translations[en][description]" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('translations.en.description', $roomType->translations['en']['description'] ?? '') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Description (Japanese)</label>
                    <textarea name="translations[ja][description]" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('translations.ja.description', $roomType->translations['ja']['description'] ?? '') }}</textarea>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Layout & occupancy</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700">Size (m²)</label>
                    <input type="number" name="size_sqm" value="{{ old('size_sqm', $roomType->size_sqm) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Max adults</label>
                    <input type="number" name="max_adults" value="{{ old('max_adults', $roomType->max_adults) }}" required min="1" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Max children</label>
                    <input type="number" name="max_children" value="{{ old('max_children', $roomType->max_children) }}" required min="0" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">View type</label>
                    <input type="text" name="view_type" value="{{ old('view_type', $roomType->view_type) }}" placeholder="ocean, garden, pool…" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Bed 1 type</label>
                    <input type="text" name="bed_type_1" value="{{ old('bed_type_1', $bed1['type'] ?? '') }}" placeholder="king" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Bed 1 count</label>
                    <input type="number" name="bed_count_1" value="{{ old('bed_count_1', $bed1['count'] ?? '') }}" min="1" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Bed 2 type</label>
                    <input type="text" name="bed_type_2" value="{{ old('bed_type_2', $bed2['type'] ?? '') }}" placeholder="twin (optional)" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Bed 2 count</label>
                    <input type="number" name="bed_count_2" value="{{ old('bed_count_2', $bed2['count'] ?? '') }}" min="1" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Pricing & inclusions</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700">Base price / night</label>
                    <input type="number" step="1000" name="base_price" value="{{ old('base_price', $roomType->base_price) }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Extra bed price / night</label>
                    <input type="number" step="1000" name="extra_bed_price" value="{{ old('extra_bed_price', $roomType->extra_bed_price) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Sort order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $roomType->sort_order) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-6">
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="breakfast_included" value="1" @checked(old('breakfast_included', $roomType->breakfast_included)) class="rounded border-stone-300">
                    Breakfast included
                </label>
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="extra_bed_available" value="1" @checked(old('extra_bed_available', $roomType->extra_bed_available)) class="rounded border-stone-300">
                    Extra bed available
                </label>
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $roomType->is_active)) class="rounded border-stone-300">
                    Published (visible on the website)
                </label>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-stone-700">Amenities (comma-separated)</label>
                <input type="text" name="amenities" value="{{ old('amenities', implode(', ', $roomType->amenities ?? [])) }}" placeholder="wifi, minibar, bathtub, balcony"
                    class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Photos</h2>

            @if ($isEdit && $roomType->images->isNotEmpty())
                <div class="mt-4 space-y-2">
                    @foreach ($roomType->images as $image)
                        <div class="flex items-center gap-3 rounded-lg border border-stone-200 p-2">
                            <img src="{{ $image->image_source }}" alt="{{ $image->alt_text }}" class="h-14 w-20 rounded object-cover">
                            <div class="flex-1 text-xs text-stone-500">
                                <p class="truncate">{{ $image->image_url }}</p>
                                <p>Tags: {{ implode(', ', $image->tags ?? []) }}</p>
                            </div>
                            <label class="flex items-center gap-1 text-xs text-red-600">
                                <input type="checkbox" name="delete_images[]" value="{{ $image->id }}" class="rounded border-stone-300">
                                Delete
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="mt-4 text-xs text-stone-500">Add photos by URL (e.g. an Unsplash or CDN link). Tag them so the AI Concierge can pick the right one — e.g. "bathroom, bathtub" or "view, balcony".</p>
            @for ($i = 0; $i < 3; $i++)
                <div class="mt-2 grid grid-cols-6 gap-2">
                    <input type="url" name="new_images[{{ $i }}][url]" placeholder="https://…" class="col-span-3 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <input type="text" name="new_images[{{ $i }}][tags]" placeholder="bedroom, view" class="col-span-2 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <input type="text" name="new_images[{{ $i }}][alt]" placeholder="Alt text" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            @endfor
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.room-types.index') }}" class="rounded-lg border border-stone-300 px-4 py-2 text-sm text-stone-600 hover:bg-stone-50">Cancel</a>
            <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">Save room type</button>
        </div>
    </form>
</x-admin-layout>
