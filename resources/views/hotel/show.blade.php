<x-hotel-stage :hotel="$hotel" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems">
    <x-slot:welcome>
        <div class="stage-welcome">
            <span class="lobby-eyebrow">{{ $hotel->name }}</span>
            <h1>{{ $lobby['welcome'] }}<br><em>{{ $lobby['lobby'] }}.</em></h1>
            <p>{{ $lobby['intro'] }}</p>
        </div>
    </x-slot:welcome>

        <div class="stage-panels">
            @include('hotel.reservation')

            <section id="lobby-staff" data-lobby-panel="staff" class="lobby-content @container" hidden tabindex="-1">
                <a href="#home" class="panel-close" aria-label="{{ $lobby['back'] }}"><span aria-hidden="true">×</span></a>
                <p class="lobby-eyebrow">{{ $hotel->name }}</p>
                <h2>{{ $labels['menu_staff'] }}</h2>
                <p class="mt-5 max-w-lg leading-relaxed text-stone-600">{{ $lobby['staff_intro'] }}</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="button" data-hero-quick-message="{{ $labels['menu_staff_q'] }}" class="lobby-action">{{ $labels['menu_staff'] }} <span aria-hidden="true">↗</span></button>
                    @if($staffLinks['whatsapp_url'])<a href="{{ $staffLinks['whatsapp_url'] }}" target="_blank" rel="noopener" class="wizard-secondary">{{ $wizard['whatsapp'] }}</a>@endif
                    @if($staffLinks['phone_url'])<a href="{{ $staffLinks['phone_url'] }}" class="wizard-secondary">{{ $wizard['call_hotel'] }}</a>@endif
                    @if($staffLinks['email_url'])<a href="{{ $staffLinks['email_url'] }}" class="wizard-secondary">{{ $wizard['email_hotel'] }}</a>@endif
                </div>
                <div class="mt-10 border-t border-stone-200 pt-6">
                    <h3 class="font-semibold">{{ $lobby['location'] }}</h3>
                    <p class="mt-2 text-sm text-stone-600">{{ $hotel->address }}, {{ $hotel->city }}, {{ $hotel->country }}</p>
                    @if($hotel->phone)<p class="mt-2 text-sm">{{ $hotel->phone }}</p>@endif
                    @if($hotel->email)<p class="mt-2 text-sm">{{ $hotel->email }}</p>@endif
                </div>
            </section>

        </div>

</x-hotel-stage>
