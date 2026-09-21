<x-hotel-stage :hotel="$hotel" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :title="$roomType->translatedName($locale).' · '.$hotel->name">
    @if($roomTypes->isNotEmpty())
        <x-slot:sidebar>@include('hotel.room-nav')</x-slot:sidebar>
    @endif

    <div class="stage-panels">
        @include('hotel.room-scene')
    </div>
</x-hotel-stage>
