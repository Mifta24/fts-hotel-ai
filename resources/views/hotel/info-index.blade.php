@php
    $about = $hotel->translatedDescription($locale);
    $mapUrl = $hotel->latitude && $hotel->longitude
        ? 'https://www.google.com/maps?q='.$hotel->latitude.','.$hotel->longitude
        : 'https://www.google.com/maps?q='.rawurlencode(trim($hotel->address.' '.$hotel->city));
    $sections = ['general' => ['heading' => $lobby['info_about'], 'icon' => 'about'], 'policies' => ['heading' => $lobby['info_policies'], 'icon' => 'policies'], 'faq' => ['heading' => $lobby['info_faq'], 'icon' => 'faq']];
@endphp
<x-hotel-stage :hotel="$hotel" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :menu="false" :title="$lobby['menu_info'].' · '.$hotel->name">
    <div class="stage-panels">
        <section class="lobby-content info-panel @container" aria-label="{{ $lobby['menu_info'] }}">
            <a href="{{ route('hotel.show', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_lobby'] }}" class="panel-close" aria-label="{{ $lobby['back'] }}"><span aria-hidden="true">×</span></a>
            <p class="lobby-eyebrow">{{ $hotel->name }}</p>
            <h2>{{ $lobby['menu_info'] }}</h2>
            @include('hotel.narrator', ['text' => $sceneNarrations['info'], 'key' => 'info', 'inline' => true])

            @if ($about)
                <p class="mt-4 leading-relaxed text-stone-600">{{ $about }}</p>
            @endif

            <div class="info-facts">
                <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="info-fact">
                    <x-info-icon name="place" />
                    <span>{{ $hotel->city ?: $lobby['info_address'] }}</span>
                </a>
                <span class="info-fact">
                    <x-info-icon name="clock" />
                    <span>{{ substr($hotel->check_in_time ?? '', 0, 5) ?: '—' }} / {{ substr($hotel->check_out_time ?? '', 0, 5) ?: '—' }}</span>
                </span>
                @if ($staffLinks['whatsapp_url'])
                    <a href="{{ $staffLinks['whatsapp_url'] }}" target="_blank" rel="noopener" class="info-fact"><x-info-icon name="contact" /><span>{{ $wizard['whatsapp'] }}</span></a>
                @elseif ($hotel->phone)
                    <a href="{{ $staffLinks['phone_url'] }}" class="info-fact"><x-info-icon name="contact" /><span>{{ $hotel->phone }}</span></a>
                @endif
            </div>

            <p class="info-address">{{ $hotel->address }}, {{ $hotel->city }}, {{ $hotel->country }}</p>

            @foreach ($sections as $category => $section)
                @if (($infoItems[$category] ?? collect())->isNotEmpty())
                    <h3 class="info-section-heading">{{ $section['heading'] }}</h3>
                    <div class="info-section-grid">
                        @foreach ($infoItems[$category] as $item)
                            <details class="info-card-item">
                                <summary>
                                    <span class="info-card-icon"><x-info-icon :name="$section['icon']" /></span>
                                    <span class="info-card-title">{{ $item->translatedTitle($locale) }}</span>
                                    <span aria-hidden="true" class="chevron">›</span>
                                </summary>
                                <p>{{ $item->translatedBody($locale) }}</p>
                            </details>
                        @endforeach
                    </div>
                @endif
            @endforeach

            <button type="button" data-hero-quick-message="{{ $labels['menu_policies_q'] }}" class="lobby-action mt-8">{{ $lobby['info_ask'] }} <span aria-hidden="true">↗</span></button>
        </section>
    </div>
</x-hotel-stage>
