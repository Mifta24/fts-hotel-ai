<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $hotel->name }} · AI Concierge</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stage-page antialiased" style="--concierge-image: url('{{ asset('images/concierge-lobby.png') }}')">
    <main class="stage" data-lobby>
        <div class="stage-loader" data-stage-loader role="status"><span class="hotel-monogram" aria-hidden="true">{{ mb_substr($hotel->name, 0, 1) }}</span><p>{{ $lobby['loading'] }}</p></div>
        <img src="{{ asset('images/concierge-lobby.png') }}" alt="{{ $lobby['assistant'] }}" class="stage-image" fetchpriority="high">
        <div class="stage-shade"></div>

        <header class="stage-header">
            <a href="{{ route('hotel.show', $hotel->slug) }}#home" class="flex min-w-0 items-center gap-3">
                <span class="hotel-monogram" aria-hidden="true">{{ mb_substr($hotel->name, 0, 1) }}</span>
                <span class="min-w-0"><span class="block truncate font-semibold tracking-tight">{{ $hotel->name }}</span><span class="block truncate text-xs opacity-75">{{ $hotel->city }} · {{ $hotel->country }}</span></span>
            </a>
            <nav aria-label="Language" class="stage-lang">
                @foreach ($supportedLocales as $code)
                    <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                @endforeach
            </nav>
        </header>

        <div class="stage-welcome">
            <span class="lobby-eyebrow">{{ $hotel->name }}</span>
            <h1>{{ $lobby['welcome'] }}<br><em>{{ $lobby['lobby'] }}.</em></h1>
            <p>{{ $lobby['intro'] }}</p>
        </div>

        <aside class="stage-menu" aria-label="{{ $lobby['explore'] }}">
            <p class="lobby-eyebrow">{{ $lobby['explore'] }}</p>
            <nav class="lobby-navigation">
                @foreach ([['rooms', $labels['rooms_heading']], ['facilities', $labels['menu_facilities']], ['info', $lobby['menu_info']], ['reservation', $lobby['reservation']], ['staff', $labels['menu_staff']]] as [$page, $label])
                    <a href="#{{ $page }}" data-lobby-link="{{ $page }}" aria-controls="lobby-{{ $page }}">
                        <span>{{ $label }}</span><span class="nav-arrow" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </nav>
            <span class="stage-menu-footer">{{ $lobby['available'] }} · POWERED BY FTS</span>
        </aside>

        <div class="stage-panels">
            <section id="lobby-rooms" data-lobby-panel="rooms" class="lobby-content @container" hidden tabindex="-1">
                <a href="#home" class="panel-close" aria-label="{{ $lobby['back'] }}"><span aria-hidden="true">×</span></a>
                <p class="lobby-eyebrow">{{ $lobby['explore'] }}</p>
                <h2>{{ $labels['rooms_heading'] }}</h2>
                @include('hotel.rooms')
                @if($roomTypes->isEmpty())<p class="mt-6 text-stone-500">{{ $lobby['rooms_empty'] }}</p>@endif
            </section>

            @if($roomTypes->isNotEmpty())
                @include('hotel.room-scene')
            @endif

            <section id="lobby-facilities" data-lobby-panel="facilities" class="lobby-content @container" hidden tabindex="-1">
                <a href="#home" class="panel-close" aria-label="{{ $lobby['back'] }}"><span aria-hidden="true">×</span></a>
                <p class="lobby-eyebrow">{{ $hotel->name }}</p>
                <h2>{{ $labels['menu_facilities'] }}</h2>
                <div class="mt-6 grid gap-4 @lg:grid-cols-2">
                    @forelse($facilities as $facility)
                        <a href="#facility/{{ $facility->id }}" class="group block rounded-2xl border border-stone-200 bg-white p-6 transition hover:border-amber-600">
                            <h3 class="font-semibold">{{ $facility->translatedTitle($locale) }}</h3>
                            <p class="mt-3 line-clamp-3 whitespace-pre-line text-sm leading-relaxed text-stone-600">{{ $facility->translatedBody($locale) }}</p>
                            <span class="mt-4 inline-flex items-center gap-2 text-xs font-medium text-amber-800">{{ $lobby['open_facility'] }} <span aria-hidden="true" class="transition group-hover:translate-x-1">→</span></span>
                        </a>
                    @empty
                        <p class="text-stone-500">{{ $lobby['empty'] }}</p>
                    @endforelse
                </div>
                <button type="button" data-hero-quick-message="{{ $labels['menu_facilities_q'] }}" class="lobby-action mt-6">{{ $labels['ask_ai'] }} <span aria-hidden="true">↗</span></button>
            </section>

            @if($facilities->isNotEmpty())
                @include('hotel.facility-scene')
            @endif

            @include('hotel.info')

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

        <div data-chat-widget>
            @include('hotel.chat')
        </div>
        <noscript><p class="stage-noscript">Aktifkan JavaScript untuk menggunakan navigasi dan AI Concierge. {{ $hotel->phone }}</p></noscript>
    </main>
</body>
</html>
