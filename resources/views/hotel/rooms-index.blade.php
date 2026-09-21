<x-hotel-stage :hotel="$hotel" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :scene-image="$sceneImage" :menu-items="$menuItems" :title="$labels['rooms_heading'].' · '.$hotel->name">
    <div class="stage-panels">
        <section class="lobby-content @container" aria-label="{{ $labels['rooms_heading'] }}">
            <a href="{{ route('hotel.show', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_lobby'] }}" class="panel-close" aria-label="{{ $lobby['back'] }}"><span aria-hidden="true">×</span></a>
            <p class="lobby-eyebrow">{{ $lobby['explore'] }}</p>
            <h2>{{ $labels['rooms_heading'] }}</h2>
            @if($roomNarrations['rooms'])
                @include('hotel.narrator', ['text' => $roomNarrations['rooms'], 'key' => 'rooms'])
            @endif
            @include('hotel.rooms')
            @if($roomTypes->isEmpty())<p class="mt-6 text-stone-500">{{ $lobby['rooms_empty'] }}</p>@endif
        </section>
    </div>
</x-hotel-stage>
