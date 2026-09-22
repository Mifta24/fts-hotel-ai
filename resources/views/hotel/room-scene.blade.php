@php
    $roomName = $roomType->translatedName($locale);
    $beds = collect($roomType->bed_config ?? [])
        ->map(fn ($bed) => $bed['count'].' × '.($roomTerms['bed'][$bed['type']] ?? Str::headline($bed['type'])))
        ->implode(', ');
    $primaryImage = $roomType->images->first();
    $roomUrl = fn ($room) => route('hotel.room', ['hotelSlug' => $hotel->slug, 'roomSlug' => $room->slug, 'lang' => $locale]);
@endphp
<section class="lobby-content @container" aria-label="{{ $roomName }}">
    <a href="{{ route('hotel.rooms', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" data-stage-exit class="panel-close" aria-label="{{ $labels['rooms_heading'] }}"><span aria-hidden="true">×</span></a>
    <article class="room-scene">
        <div class="flex flex-wrap items-center justify-between gap-3 pr-12">
            <p class="lobby-eyebrow">{{ $lobby['room_counter'] }} {{ $roomIndex + 1 }} / {{ $roomTypes->count() }}</p>
            @if ($roomTypes->count() > 1)
                <nav class="flex gap-2" aria-label="{{ $lobby['room_scene'] }}">
                    <a href="{{ $roomUrl($previousRoom) }}" data-stage-exit class="room-scene-step" rel="prev"><span aria-hidden="true">←</span> {{ $lobby['prev_room'] }}</a>
                    <a href="{{ $roomUrl($nextRoom) }}" data-stage-exit class="room-scene-step" rel="next">{{ $lobby['next_room'] }} <span aria-hidden="true">→</span></a>
                </nav>
            @endif
        </div>

        @include('hotel.narrator', ['text' => $roomNarrations['room'][$roomType->slug], 'key' => 'room-'.$roomType->slug])

        <div class="mt-5 grid gap-7 @2xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <div data-room-gallery>
                <div class="aspect-[4/3] w-full overflow-hidden rounded-2xl bg-stone-200">
                    @if ($primaryImage)
                        <img src="{{ $primaryImage->image_source }}" alt="{{ $primaryImage->alt_text }}" class="h-full w-full object-cover" data-room-gallery-main>
                    @else
                        <div class="grid h-full place-items-center text-sm text-stone-500">{{ $lobby['no_photo'] }}</div>
                    @endif
                </div>
                @if ($roomType->images->count() > 1)
                    <ul class="mt-3 grid grid-cols-4 gap-2" aria-label="{{ $lobby['gallery'] }}">
                        @foreach ($roomType->images as $imageIndex => $image)
                            <li>
                                <button type="button" class="room-scene-thumb" data-room-thumb data-src="{{ $image->image_source }}" data-alt="{{ $image->alt_text }}" aria-label="{{ $lobby['photo'] }} {{ $imageIndex + 1 }}" @if($imageIndex === 0) aria-current="true" @endif>
                                    <img src="{{ $image->image_source }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="min-w-0">
                <h2 class="mt-0!">{{ $roomName }}</h2>
                <p class="mt-3 leading-relaxed text-stone-600">{{ $roomType->translatedDescription($locale) }}</p>

                <p class="mt-5 text-sm text-stone-500">
                    {{ $labels['from'] }}
                    <span class="text-2xl font-semibold text-stone-900">{{ $hotel->currency }} {{ number_format((float) $roomType->base_price, 0, ',', '.') }}</span>
                    {{ $labels['per_night'] }}
                </p>
                <p class="mt-1 text-xs text-stone-500">{{ $lobby['availability_note'] }}</p>

                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('hotel.reservation', ['hotelSlug' => $hotel->slug, 'lang' => $locale, 'room' => $roomType->slug]) }}" data-stage-exit data-tour-line="{{ $narration['tour_reservation'] }}" class="lobby-action">{{ $lobby['reserve_room'] }} <span aria-hidden="true">↗</span></a>
                    <button type="button" class="room-scene-step" data-hero-quick-message="{{ str_replace(':name', $roomName, $lobby['ask_room_q']) }}">{{ $lobby['ask_room'] }}</button>
                </div>

                <dl class="room-scene-facts">
                    @if ($roomType->size_sqm)
                        <div><dt>{{ $lobby['size'] }}</dt><dd>{{ $roomType->size_sqm }} m²</dd></div>
                    @endif
                    @if ($beds !== '')
                        <div><dt>{{ $lobby['bed'] }}</dt><dd>{{ $beds }}</dd></div>
                    @endif
                    <div><dt>{{ $lobby['guests'] }}</dt><dd>{{ $roomType->maxOccupancy() }} {{ $labels['max_guests'] }}</dd></div>
                    @if ($roomType->view_type)
                        <div><dt>{{ $lobby['view'] }}</dt><dd>{{ $roomTerms['view'][$roomType->view_type] ?? Str::headline($roomType->view_type) }}</dd></div>
                    @endif
                    <div><dt>{{ $lobby['breakfast'] }}</dt><dd>{{ $roomType->breakfast_included ? $labels['breakfast_included'] : $lobby['breakfast_excluded'] }}</dd></div>
                    <div>
                        <dt>{{ $lobby['extra_bed'] }}</dt>
                        <dd>
                            @if ($roomType->extra_bed_available)
                                {{ $lobby['extra_bed_yes'] }}@if ($roomType->extra_bed_price > 0) (+{{ $hotel->currency }} {{ number_format((float) $roomType->extra_bed_price, 0, ',', '.') }})@endif
                            @else
                                {{ $lobby['extra_bed_no'] }}
                            @endif
                        </dd>
                    </div>
                </dl>

                @if (! empty($roomType->amenities))
                    <h3 class="mt-6 text-sm font-semibold">{{ $lobby['amenities'] }}</h3>
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($roomType->amenities as $amenity)
                            <li class="rounded-full border border-stone-300 bg-white px-3 py-1 text-xs text-stone-600">{{ $roomTerms['amenity'][$amenity] ?? Str::headline($amenity) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </article>
</section>
