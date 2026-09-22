<x-hotel-stage :hotel="$hotel" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :menu="false" :title="$lobby['reservation'].' · '.$hotel->name">
    <div class="stage-panels">
        @include('hotel.reservation')
    </div>
</x-hotel-stage>
