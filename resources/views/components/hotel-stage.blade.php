@props(['hotel', 'locale', 'supportedLocales', 'labels', 'lobby', 'narration', 'scene', 'backdrop', 'menuItems', 'title' => null, 'welcome' => null, 'sidebar' => null, 'menu' => true])
{{-- The fixed full-screen stage every scene shares: backdrop, header, menu and the concierge chat dock. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $hotel->name.' · AI Concierge' }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stage-page antialiased" style="--concierge-image: url('{{ $backdrop['avatarImage'] }}'); --stage-focus: {{ $backdrop['focus'] }}; --stage-focus-mobile: {{ $backdrop['focusMobile'] }}; --concierge-zoom: {{ $backdrop['avatarZoom'] }}; --concierge-focus: {{ $backdrop['avatarFocus'] }}">
    <main class="stage" data-lobby data-scene="{{ $scene }}" data-page="{{ $scene === 'lobby' ? 'home' : $scene }}">
        <div class="stage-loader" data-stage-loader role="status"><span class="hotel-monogram" aria-hidden="true">{{ mb_substr($hotel->name, 0, 1) }}</span><p>{{ $lobby['loading'] }}</p></div>
        <img src="{{ $backdrop['image'] }}" alt="{{ $backdrop['character'] ? '' : $lobby['assistant'] }}" class="stage-image" fetchpriority="high">
        <div class="stage-shade"></div>
        @if ($backdrop['character'])
            <img src="{{ $backdrop['character'] }}" alt="{{ $lobby['assistant'] }}" class="stage-character" data-anchor="{{ $backdrop['anchor'] }}">
        @endif
        <p class="stage-tour" data-stage-tour aria-live="polite"></p>

        <header class="stage-header">
            <a href="{{ route('hotel.show', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" @if($scene !== 'lobby') data-stage-exit data-tour-line="{{ $narration['tour_lobby'] }}" @endif class="flex min-w-0 items-center gap-3">
                <span class="hotel-monogram" aria-hidden="true">{{ mb_substr($hotel->name, 0, 1) }}</span>
                <span class="min-w-0"><span class="block truncate font-semibold tracking-tight">{{ $hotel->name }}</span><span class="block truncate text-xs opacity-75">{{ $hotel->city }} · {{ $hotel->country }}</span></span>
            </a>
            <div class="stage-tools">
                <x-sound-toggle :on="$lobby['sound_on']" :off="$lobby['sound_off']" />
                <nav aria-label="Language" class="stage-lang">
                    @foreach ($supportedLocales as $code)
                        <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        {{ $welcome }}

@if(! $menu)
@elseif($sidebar)
        {{ $sidebar }}
@else
        <aside class="stage-menu" aria-label="{{ $lobby['explore'] }}">
            <p class="lobby-eyebrow">{{ $lobby['explore'] }}</p>
            <nav class="lobby-navigation">
                @foreach ($menuItems as $item)
                    <a href="{{ $item['href'] }}"
                        @if($item['exit']) data-stage-exit @if($item['tour']) data-tour-line="{{ $item['tour'] }}" @endif @else data-lobby-link="{{ $item['key'] }}" aria-controls="lobby-{{ $item['key'] }}" @endif
                        @if($item['key'] === $scene || ($scene === 'room' && $item['key'] === 'rooms')) aria-current="page" @endif>
                        <span>{{ $item['label'] }}</span><span class="nav-arrow" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </nav>
            <span class="stage-menu-footer">{{ $lobby['available'] }} · POWERED BY FTS</span>
        </aside>
@endif

        {{ $slot }}

        <div data-chat-widget>
            @include('hotel.chat')
        </div>
        <noscript><p class="stage-noscript">Aktifkan JavaScript untuk menggunakan navigasi dan AI Concierge. {{ $hotel->phone }}</p></noscript>
    </main>
</body>
</html>
