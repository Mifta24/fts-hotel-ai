<x-hotel-stage :hotel="$hotel" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :menu="false" :title="$facility->translatedTitle($locale).' · '.$hotel->name">
    @php
        $title = $facility->translatedTitle($locale);
        $facilityUrl = fn ($item) => route('hotel.facility', ['hotelSlug' => $hotel->slug, 'facilityId' => $item->id, 'lang' => $locale]);
    @endphp

    <div class="stage-panels">
        <section class="lobby-content stage-panel-right @container" aria-label="{{ $title }}">
            <a href="{{ route('hotel.facilities', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" data-stage-exit class="panel-close" aria-label="{{ $lobby['back_facilities'] }}"><span aria-hidden="true">×</span></a>
            <article class="room-scene">
                <div class="flex flex-wrap items-center justify-between gap-3 pr-12">
                    <p class="lobby-eyebrow">{{ $lobby['facility_counter'] }} {{ $facilityIndex + 1 }} / {{ $facilities->count() }}</p>
                    @if ($facilities->count() > 1)
                        <nav class="flex gap-2" aria-label="{{ $labels['menu_facilities'] }}">
                            <a href="{{ $facilityUrl($previousFacility) }}" data-stage-exit class="room-scene-step" rel="prev"><span aria-hidden="true">←</span> {{ $lobby['prev_facility'] }}</a>
                            <a href="{{ $facilityUrl($nextFacility) }}" data-stage-exit class="room-scene-step" rel="next">{{ $lobby['next_facility'] }} <span aria-hidden="true">→</span></a>
                        </nav>
                    @endif
                </div>
                @if ($facility->image_url)
                    <div class="facility-hero mt-5">
                        <img src="{{ $facility->image_url }}" alt="{{ $title }}">
                        <span class="facility-card-icon"><x-facility-icon :name="$facility->iconName()" /></span>
                    </div>
                @endif
                <h2>{{ $title }}</h2>
                @include('hotel.narrator', ['text' => $facility->translatedBody($locale), 'key' => 'facility-'.$facility->id, 'inline' => true])
                <div class="mt-8 flex flex-wrap gap-3">
                    <button type="button" class="lobby-action" data-hero-quick-message="{{ str_replace(':name', $title, $lobby['ask_facility_q']) }}">{{ $lobby['ask_facility'] }} <span aria-hidden="true">↗</span></button>
                    <a href="{{ route('hotel.facilities', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" data-stage-exit class="room-scene-step">{{ $lobby['back_facilities'] }}</a>
                </div>
            </article>
        </section>
    </div>
</x-hotel-stage>
