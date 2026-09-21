{{-- On the rooms scenes the menu card becomes the room index, so the guest can step between rooms without going back. --}}
<aside class="stage-menu" aria-label="{{ $labels['rooms_heading'] }}">
    <a href="{{ route('hotel.show', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_lobby'] }}" class="stage-menu-back"><span aria-hidden="true">‹</span> {{ $lobby['explore'] }}</a>
    <p class="lobby-eyebrow">{{ $labels['rooms_heading'] }}</p>
    <nav class="room-nav">
        @foreach ($roomTypes as $item)
            <a href="{{ route('hotel.room', ['hotelSlug' => $hotel->slug, 'roomSlug' => $item->slug, 'lang' => $locale]) }}"
                data-stage-exit
                @if (($roomType ?? null)?->slug === $item->slug) aria-current="page" @endif>
                @if ($item->images->first())
                    <img class="room-nav-thumb" src="{{ $item->images->first()->image_source }}" alt="" loading="lazy">
                @endif
                <span class="room-nav-text">
                    <span class="room-nav-name">{{ $item->translatedName($locale) }}</span>
                    <span class="room-nav-price">{{ $labels['from'] }} {{ $hotel->currency }} {{ number_format((float) $item->base_price, 0, ',', '.') }} {{ $labels['per_night'] }}</span>
                </span>
            </a>
        @endforeach
    </nav>
    <span class="stage-menu-footer">{{ $lobby['available'] }} · POWERED BY FTS</span>
</aside>
