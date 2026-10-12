<x-hotel-stage :hotel="$hotel" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :wizard="$wizard" :room-terms="$roomTerms" :today="$today" :tour-stops="$tourStops">
    <x-slot:welcome>
        <div class="stage-welcome">
            <span class="lobby-eyebrow">{{ $hotel->name }}</span>
            <h1>{{ $lobby['welcome'] }}<br><em>{{ $lobby['lobby'] }}.</em></h1>
            <p>{{ $lobby['intro'] }}</p>
        </div>
    </x-slot:welcome>
</x-hotel-stage>
