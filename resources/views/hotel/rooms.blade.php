        <div class="mt-6 grid grid-cols-1 gap-6 @lg:grid-cols-2 @4xl:grid-cols-3">
            @foreach ($roomTypes as $roomType)
                @php $thumbnail = $roomType->images->first(); @endphp
                <article
                    class="group overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm transition hover:shadow-md"
                    data-room-card
                    data-room-slug="{{ $roomType->slug }}"
                    data-room-name="{{ $roomType->translatedName($locale) }}"
                >
                    <div class="aspect-[4/3] w-full overflow-hidden bg-stone-100">
                        @if ($thumbnail)
                            <img
                                src="{{ $thumbnail->image_source }}"
                                alt="{{ $thumbnail->alt_text }}"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                loading="lazy"
                            >
                        @endif
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-stone-900">{{ $roomType->translatedName($locale) }}</h3>
                        <p class="mt-1 line-clamp-2 text-sm text-stone-600">{{ $roomType->translatedDescription($locale) }}</p>

                        <dl class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-stone-500">
                            <div>{{ $roomType->size_sqm }} m²</div>
                            <div>{{ $roomType->maxOccupancy() }} {{ $labels['max_guests'] }}</div>
                            @if ($roomType->breakfast_included)
                                <div>{{ $labels['breakfast_included'] }}</div>
                            @endif
                        </dl>

                        <div class="mt-4 flex items-center justify-between">
                            <p class="text-sm text-stone-500">
                                {{ $labels['from'] }}
                                <span class="text-base font-semibold text-stone-900">
                                    {{ $hotel->currency }} {{ number_format((float) $roomType->base_price, 0, ',', '.') }}
                                </span>
                                {{ $labels['per_night'] }}
                            </p>
                        </div>

                        <a href="#room/{{ $roomType->slug }}" class="mt-4 flex w-full items-center justify-center rounded-lg border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 transition hover:bg-stone-50">
                            {{ $labels['view_details'] }}
                        </a>

                        <button
                            type="button"
                            data-ask-ai-button
                            class="mt-2 flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-amber-600 to-amber-400 px-3 py-2 text-sm font-medium text-white transition hover:opacity-90"
                        >
                            <x-ai-mark size="4" class="!rounded !bg-white/20 !shadow-none" />
                            {{ $labels['ask_ai'] }}
                        </button>
                    </div>
                </article>
            @endforeach
        </div>
