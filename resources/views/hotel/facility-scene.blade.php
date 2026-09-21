@php $items = $facilities->values(); @endphp
<section id="lobby-facility" data-lobby-panel="facility" class="lobby-content @container" hidden tabindex="-1" aria-label="{{ $labels['menu_facilities'] }}">
    <a href="#facilities" class="panel-close" aria-label="{{ $lobby['back_facilities'] }}"><span aria-hidden="true">×</span></a>
    @foreach ($items as $index => $facility)
        @php
            $title = $facility->translatedTitle($locale);
            $previous = $items[($index - 1 + $items->count()) % $items->count()];
            $next = $items[($index + 1) % $items->count()];
        @endphp
        <article data-facility-scene="{{ $facility->id }}" class="room-scene" hidden>
            <div class="flex flex-wrap items-center justify-between gap-3 pr-12">
                <p class="lobby-eyebrow">{{ $lobby['facility_counter'] }} {{ $index + 1 }} / {{ $items->count() }}</p>
                @if ($items->count() > 1)
                    <nav class="flex gap-2" aria-label="{{ $labels['menu_facilities'] }}">
                        <a href="#facility/{{ $previous->id }}" class="room-scene-step" rel="prev"><span aria-hidden="true">←</span> {{ $lobby['prev_facility'] }}</a>
                        <a href="#facility/{{ $next->id }}" class="room-scene-step" rel="next">{{ $lobby['next_facility'] }} <span aria-hidden="true">→</span></a>
                    </nav>
                @endif
            </div>
            <h2 class="mt-6">{{ $title }}</h2>
            <p class="mt-5 max-w-2xl whitespace-pre-line leading-relaxed text-stone-600">{{ $facility->translatedBody($locale) }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <button type="button" class="lobby-action" data-hero-quick-message="{{ str_replace(':name', $title, $lobby['ask_facility_q']) }}" data-facility-id="{{ $facility->id }}">{{ $lobby['ask_facility'] }} <span aria-hidden="true">↗</span></button>
                <a href="#facilities" class="room-scene-step">{{ $lobby['back_facilities'] }}</a>
            </div>
        </article>
    @endforeach
</section>
