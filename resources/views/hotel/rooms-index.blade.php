<x-hotel-stage :hotel="$hotel" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :title="$labels['rooms_heading'].' · '.$hotel->name">
    <x-slot:welcome>
        <div class="stage-welcome stage-welcome-rooms">
            <span class="lobby-eyebrow">{{ $lobby['explore'] }}</span>
            <h1>{{ $labels['rooms_heading'] }}</h1>
            @if ($roomNarrations['rooms'])
                @include('hotel.narrator', ['text' => $roomNarrations['rooms'], 'key' => 'rooms', 'onStage' => true])
            @else
                <p>{{ $lobby['rooms_empty'] }}</p>
            @endif
        </div>
    </x-slot:welcome>

    @if ($roomTypes->isNotEmpty())
        <x-slot:sidebar>@include('hotel.room-nav')</x-slot:sidebar>
    @endif
</x-hotel-stage>
