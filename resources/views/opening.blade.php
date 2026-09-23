<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FTS Hotel AI</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stage-page antialiased">
    <main class="stage">
        <div class="stage-loader" data-stage-loader role="status"><span class="hotel-monogram" aria-hidden="true">F</span><p>{{ $opening['loading'] }}</p></div>
        <img src="{{ asset('images/concierge-lobby.webp') }}" alt="" class="stage-image" fetchpriority="high">
        <div class="stage-shade"></div>

        <header class="stage-header">
            <span class="flex min-w-0 items-center gap-3">
                <span class="hotel-monogram" aria-hidden="true">F</span>
                <span class="block truncate font-semibold tracking-tight">FTS Hotel AI</span>
            </span>
            <div class="stage-tools">
                <x-sound-toggle :on="$opening['sound_on']" :off="$opening['sound_off']" />
                <nav aria-label="Language" class="stage-lang">
                    @foreach ($supportedLocales as $code)
                        <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        <section class="opening-panel" aria-labelledby="opening-title">
            <p class="lobby-eyebrow">AI CONCIERGE</p>
            <h1 id="opening-title">{{ $opening['welcome'] }}<br><em>FTS Hotel AI</em></h1>
            <p class="opening-tagline">{{ $opening['tagline'] }}</p>

            @if ($hotels->count() === 1)
                @php
                    $hotelSlug = $hotels->first()->slug;
                    $openingLinks = [
                        'rooms' => route('hotel.rooms', ['hotelSlug' => $hotelSlug, 'lang' => $locale]),
                        'facilities' => route('hotel.facilities', ['hotelSlug' => $hotelSlug, 'lang' => $locale]),
                        'reservation' => route('hotel.reservation', ['hotelSlug' => $hotelSlug, 'lang' => $locale]),
                        'staff' => route('hotel.staff', ['hotelSlug' => $hotelSlug, 'lang' => $locale]),
                    ];
                @endphp
                <ul class="opening-links">
                    @foreach ($openingLinks as $key => $href)
                        <li><a data-stage-exit href="{{ $href }}"><span>{{ $opening[$key] }}</span><span aria-hidden="true">›</span></a></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <div class="opening-enter">
            @forelse ($hotels as $hotel)
                <a href="{{ route('hotel.show', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" class="opening-button" data-stage-exit>
                    {{ str_replace(':name', $hotel->name, $opening['enter']) }} <span aria-hidden="true">→</span>
                </a>
            @empty
                <p class="opening-empty">{{ $opening['empty'] }}</p>
            @endforelse
        </div>
    </main>
</body>
</html>
