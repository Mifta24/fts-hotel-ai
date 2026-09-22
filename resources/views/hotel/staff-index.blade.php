<x-hotel-stage :hotel="$hotel" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :menu="false" :title="$labels['menu_staff'].' · '.$hotel->name">
    <div class="stage-panels">
        <section class="lobby-content staff-panel @container" aria-label="{{ $labels['menu_staff'] }}">
            <a href="{{ route('hotel.show', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_lobby'] }}" class="panel-close" aria-label="{{ $lobby['back'] }}"><span aria-hidden="true">×</span></a>
            <p class="lobby-eyebrow">{{ $hotel->name }}</p>
            <h2>{{ $labels['menu_staff'] }}</h2>
            @include('hotel.narrator', ['text' => $lobby['staff_intro'], 'key' => 'staff', 'inline' => true])

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="button" data-hero-quick-message="{{ $labels['menu_staff_q'] }}" class="lobby-action">{{ $labels['menu_staff'] }} <span aria-hidden="true">↗</span></button>
                @if($staffLinks['whatsapp_url'])<a href="{{ $staffLinks['whatsapp_url'] }}" target="_blank" rel="noopener" class="wizard-secondary">{{ $wizard['whatsapp'] }}</a>@endif
                @if($staffLinks['phone_url'])<a href="{{ $staffLinks['phone_url'] }}" class="wizard-secondary">{{ $wizard['call_hotel'] }}</a>@endif
                @if($staffLinks['email_url'])<a href="{{ $staffLinks['email_url'] }}" class="wizard-secondary">{{ $wizard['email_hotel'] }}</a>@endif
            </div>

            <div class="staff-location">
                <h3>{{ $lobby['location'] }}</h3>
                <p>{{ $hotel->address }}, {{ $hotel->city }}, {{ $hotel->country }}</p>
                @if($hotel->phone)<p>{{ $hotel->phone }}</p>@endif
                @if($hotel->email)<p>{{ $hotel->email }}</p>@endif
            </div>
        </section>
    </div>
</x-hotel-stage>
