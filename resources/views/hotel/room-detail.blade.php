<x-hotel-stage :hotel="$hotel" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :scene-image="$sceneImage" :menu-items="$menuItems" :title="$roomType->translatedName($locale).' · '.$hotel->name">
    <div class="stage-panels">
        @include('hotel.room-scene')
    </div>
</x-hotel-stage>
