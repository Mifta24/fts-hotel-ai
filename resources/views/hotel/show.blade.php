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
<body class="lobby-page antialiased" style="--concierge-image: url('{{ asset('images/concierge-lobby.png') }}')">
    <header class="lobby-header">
        <a href="{{ route('hotel.show', $hotel->slug) }}" class="flex min-w-0 items-center gap-3">
            <span class="hotel-monogram" aria-hidden="true">{{ mb_substr($hotel->name, 0, 1) }}</span>
            <span class="min-w-0"><span class="block truncate font-semibold tracking-tight">{{ $hotel->name }}</span><span class="block text-xs text-stone-500">{{ $hotel->city }} · {{ $hotel->country }}</span></span>
        </a>
        <div class="flex items-center gap-6">
            <span class="header-concierge text-xs tracking-widest text-stone-500">YOUR STAY, BEAUTIFULLY CONNECTED</span>
            <nav aria-label="Language" class="flex gap-1 rounded-full border border-stone-200 p-1 text-xs">
                @foreach ($supportedLocales as $code)
                    <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif class="rounded-full px-3 py-1.5 uppercase {{ $code === $locale ? 'bg-stone-800 text-white' : 'text-stone-500 hover:bg-stone-100' }}">{{ $code }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="lobby-layout" data-lobby>
        <div class="lobby-main">
            <section id="lobby-home" data-lobby-panel="home" class="lobby-scene" aria-label="{{ $lobby['home'] }}">
                <img src="{{ asset('images/concierge-lobby.png') }}" alt="{{ $lobby['assistant'] }}" class="lobby-scene-image" fetchpriority="high">
                <div class="lobby-scene-shade"></div>
                <div class="lobby-welcome">
                    <span class="lobby-eyebrow">THE AI CONCIERGE EXPERIENCE</span>
                    <h1>{{ $lobby['welcome'] }}<br><em>{{ $lobby['lobby'] }}.</em></h1>
                    <p>{{ $lobby['intro'] }}</p>
                </div>
                <span class="scene-illustration">{{ $lobby['illustration'] }}</span>
                <div class="host-caption">
                    <span class="host-status" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                    <div><strong>{{ $labels['chat_heading'] }}</strong><span>{{ $lobby['assistant'] }}</span></div>
                    <span class="host-ready"><span class="status-dot"></span> 24/7</span>
                </div>
            </section>

            <section id="lobby-rooms" data-lobby-panel="rooms" class="lobby-content" hidden tabindex="-1">
                <p class="lobby-eyebrow">{{ $lobby['explore'] }}</p>
                <h2>{{ $labels['rooms_heading'] }}</h2>
                @include('hotel.rooms')
                @if($roomTypes->isEmpty())<p class="mt-6 text-stone-500">{{ $lobby['rooms_empty'] }}</p>@endif
            </section>

            <section id="lobby-facilities" data-lobby-panel="facilities" class="lobby-content" hidden tabindex="-1">
                <p class="lobby-eyebrow">{{ $hotel->name }}</p>
                <h2>{{ $labels['menu_facilities'] }}</h2>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    @forelse($facilities as $facility)
                        <article class="rounded-2xl border border-stone-200 bg-white p-6">
                            <h3 class="font-semibold">{{ $facility->translatedTitle($locale) }}</h3>
                            <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-stone-600">{{ $facility->translatedBody($locale) }}</p>
                        </article>
                    @empty
                        <p class="text-stone-500">{{ $lobby['empty'] }}</p>
                    @endforelse
                </div>
                <button type="button" data-hero-quick-message="{{ $labels['menu_facilities_q'] }}" class="lobby-action mt-6">{{ $labels['ask_ai'] }} <span aria-hidden="true">↗</span></button>
            </section>

            <section id="lobby-reservation" data-lobby-panel="reservation" class="lobby-content" hidden tabindex="-1">
                <p class="lobby-eyebrow">{{ $hotel->name }}</p>
                <h2>{{ $lobby['reservation'] }}</h2>
                <p class="mt-5 max-w-lg leading-relaxed text-stone-600">{{ $lobby['reservation_intro'] }}</p>
                <div class="my-8 flex flex-wrap gap-10 border-y border-stone-200 py-6">
                    <div><p class="text-xs text-stone-500">{{ $lobby['check_in'] }}</p><p class="mt-2 text-2xl">{{ substr($hotel->check_in_time, 0, 5) }}</p></div>
                    <div><p class="text-xs text-stone-500">{{ $lobby['check_out'] }}</p><p class="mt-2 text-2xl">{{ substr($hotel->check_out_time, 0, 5) }}</p></div>
                </div>
                <button type="button" data-hero-quick-message="{{ $lobby['reservation_q'] }}" class="lobby-action">{{ $lobby['start_booking'] }} <span aria-hidden="true">↗</span></button>
            </section>

            <section id="lobby-staff" data-lobby-panel="staff" class="lobby-content" hidden tabindex="-1">
                <p class="lobby-eyebrow">{{ $hotel->name }}</p>
                <h2>{{ $labels['menu_staff'] }}</h2>
                <p class="mt-5 max-w-lg leading-relaxed text-stone-600">{{ $lobby['staff_intro'] }}</p>
                <button type="button" data-hero-quick-message="{{ $labels['menu_staff_q'] }}" class="lobby-action mt-6">{{ $labels['menu_staff'] }} <span aria-hidden="true">↗</span></button>
                <div class="mt-10 border-t border-stone-200 pt-6">
                    <h3 class="font-semibold">{{ $lobby['location'] }}</h3>
                    <p class="mt-2 text-sm text-stone-600">{{ $hotel->address }}, {{ $hotel->city }}, {{ $hotel->country }}</p>
                    @if($hotel->phone)<p class="mt-2 text-sm">{{ $hotel->phone }}</p>@endif
                    @if($hotel->email)<p class="mt-2 text-sm">{{ $hotel->email }}</p>@endif
                </div>
            </section>

            <div data-chat-widget>
                @include('hotel.chat')
            </div>
            <noscript><p class="rounded-xl bg-amber-50 p-4">Aktifkan JavaScript untuk menggunakan navigasi dan AI Concierge. {{ $hotel->phone }}</p></noscript>
        </div>

        <aside class="lobby-sidebar">
            <div class="sidebar-heading"><span class="lobby-eyebrow">{{ $lobby['explore'] }}</span><p>{{ $hotel->name }}</p></div>
            <nav aria-label="{{ $lobby['explore'] }}" class="lobby-navigation">
                @foreach ([['home', '01', $lobby['home']], ['rooms', '02', $labels['rooms_heading']], ['facilities', '03', $labels['menu_facilities']], ['reservation', '04', $lobby['reservation']], ['staff', '05', $labels['menu_staff']]] as [$page, $number, $label])
                    <a href="#{{ $page }}" data-lobby-link="{{ $page }}" aria-controls="lobby-{{ $page }}" @if($page === 'home') aria-current="page" @endif>
                        <span class="nav-number">{{ $number }}</span><span>{{ $label }}</span><span class="nav-arrow" aria-hidden="true">↗</span>
                    </a>
                @endforeach
            </nav>
            <div class="sidebar-note">
                <span class="status-dot"></span><span>{{ $lobby['available'] }}</span>
                <p>{{ $labels['chat_subtitle'] }}</p>
                <button type="button" data-focus-chat>{{ $labels['ask_ai'] }} <span aria-hidden="true">↗</span></button>
            </div>
            <div class="sidebar-location"><span class="lobby-eyebrow">{{ $lobby['location'] }}</span><p>{{ $hotel->city }}, {{ $hotel->country }}</p><span>{{ $hotel->address }}</span></div>
            <span class="sidebar-footer">POWERED BY FTS · AI CONCIERGE</span>
        </aside>
    </main>
</body>
</html>
