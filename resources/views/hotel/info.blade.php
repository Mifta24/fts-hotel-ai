@php
    $about = $hotel->translatedDescription($locale);
    $mapUrl = $hotel->latitude && $hotel->longitude
        ? 'https://www.google.com/maps?q='.$hotel->latitude.','.$hotel->longitude
        : 'https://www.google.com/maps?q='.rawurlencode(trim($hotel->address.' '.$hotel->city));
    $sections = ['general' => $lobby['info_about'], 'policies' => $lobby['info_policies'], 'faq' => $lobby['info_faq']];
@endphp
<section id="lobby-info" data-lobby-panel="info" class="lobby-content @container" hidden tabindex="-1" aria-label="{{ $lobby['menu_info'] }}">
    <a href="#home" class="panel-close" aria-label="{{ $lobby['back'] }}"><span aria-hidden="true">×</span></a>
    <p class="lobby-eyebrow">{{ $hotel->name }}</p>
    <h2>{{ $lobby['menu_info'] }}</h2>

    @if ($about)
        <p class="mt-4 max-w-2xl leading-relaxed text-stone-600">{{ $about }}</p>
    @endif

    <dl class="room-scene-facts mt-6 @2xl:grid-cols-3">
        <div>
            <dt>{{ $lobby['info_address'] }}</dt>
            <dd>{{ $hotel->address }}, {{ $hotel->city }}, {{ $hotel->country }}<br><a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="text-sm text-amber-800 underline">{{ $lobby['info_map'] }}</a></dd>
        </div>
        <div>
            <dt>{{ $lobby['info_hours'] }}</dt>
            <dd>{{ substr($hotel->check_in_time ?? '', 0, 5) ?: '—' }} / {{ substr($hotel->check_out_time ?? '', 0, 5) ?: '—' }}</dd>
        </div>
        <div>
            <dt>{{ $lobby['info_contact'] }}</dt>
            <dd class="flex flex-wrap gap-x-4 gap-y-1">
                @if ($staffLinks['whatsapp_url'])<a href="{{ $staffLinks['whatsapp_url'] }}" target="_blank" rel="noopener" class="underline">{{ $wizard['whatsapp'] }}</a>@endif
                @if ($staffLinks['phone_url'])<a href="{{ $staffLinks['phone_url'] }}" class="underline">{{ $hotel->phone }}</a>@endif
                @if ($staffLinks['email_url'])<a href="{{ $staffLinks['email_url'] }}" class="underline">{{ $hotel->email }}</a>@endif
            </dd>
        </div>
    </dl>

    @foreach ($sections as $category => $heading)
        @if (($infoItems[$category] ?? collect())->isNotEmpty())
            <h3 class="mt-8 text-sm font-semibold">{{ $heading }}</h3>
            <div class="mt-3 divide-y divide-stone-300 border-y border-stone-300">
                @foreach ($infoItems[$category] as $item)
                    <details class="group py-3">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-medium">
                            {{ $item->translatedTitle($locale) }}
                            <span aria-hidden="true" class="text-stone-400 transition group-open:rotate-90">›</span>
                        </summary>
                        <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-stone-600">{{ $item->translatedBody($locale) }}</p>
                    </details>
                @endforeach
            </div>
        @endif
    @endforeach

    <button type="button" data-hero-quick-message="{{ $labels['menu_policies_q'] }}" class="lobby-action mt-8">{{ $lobby['info_ask'] }} <span aria-hidden="true">↗</span></button>
</section>
