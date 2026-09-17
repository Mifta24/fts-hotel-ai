<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $hotel->name }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 text-stone-900 antialiased">

    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <x-ai-mark size="9" />
                <div>
                    <p class="text-lg font-semibold tracking-tight">{{ $hotel->name }}</p>
                    <p class="text-sm text-stone-500">{{ $hotel->city }}, {{ $hotel->country }}</p>
                </div>
            </div>
            <nav class="flex items-center gap-1 text-sm">
                @foreach ($supportedLocales as $code)
                    <a
                        href="?lang={{ $code }}"
                        class="rounded-md px-2 py-1 uppercase {{ $code === $locale ? 'bg-gradient-to-br from-amber-600 to-amber-400 text-white' : 'text-stone-500 hover:bg-stone-100' }}"
                    >{{ $code }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    {{-- Hero: AI Concierge introduces itself over the hotel's own photo,
         with quick-start topics right there — no need to open the chat
         bubble first to see what the AI can help with. --}}
    <section class="relative overflow-hidden">
        @if ($hotel->cover_path)
            <img src="{{ $hotel->cover_path }}" alt="" class="absolute inset-0 h-full w-full object-cover">
        @endif
        <div class="absolute inset-0 bg-gradient-to-r from-stone-900/85 via-stone-900/60 to-stone-900/30"></div>

        <div class="relative mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 items-center gap-8 lg:grid-cols-2">
                {{-- Left: AI Concierge + hotel intro --}}
                <div class="text-white">
                    <div class="flex items-center gap-3">
                        <x-concierge-avatar size="12" />
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-amber-200 ring-1 ring-white/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            {{ $labels['chat_heading'] }} · 24/7
                        </span>
                    </div>
                    <h1 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $hotel->name }}</h1>
                    <p class="mt-3 max-w-md text-white/80">{{ $hotel->translatedDescription($locale) }}</p>
                    <p class="mt-3 text-sm text-white/60">{{ $hotel->address }}, {{ $hotel->city }}, {{ $hotel->country }}</p>
                </div>

                {{-- Right: quick-start menu, opens the chat with that topic --}}
                <div class="w-full justify-self-start rounded-2xl bg-white/10 p-2 ring-1 ring-white/20 backdrop-blur-sm lg:justify-self-end lg:max-w-sm">
                    <p class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white/50">{{ $labels['menu_heading'] }}</p>
                    <div class="space-y-1">
                        @foreach ([
                            [$labels['menu_rooms'], $labels['menu_rooms_q']],
                            [$labels['menu_facilities'], $labels['menu_facilities_q']],
                            [$labels['menu_policies'], $labels['menu_policies_q']],
                            [$labels['menu_staff'], $labels['menu_staff_q']],
                        ] as [$label, $question])
                            <button
                                type="button"
                                data-hero-quick-message="{{ $question }}"
                                class="flex w-full items-center justify-between rounded-lg px-3 py-3 text-left text-sm text-white transition hover:bg-white/10"
                            >
                                <span>{{ $label }}</span>
                                <span class="text-white/40">›</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <main class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <h2 class="text-xl font-semibold text-stone-900">{{ $labels['rooms_heading'] }}</h2>

        <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($roomTypes as $roomType)
                @php $thumbnail = $roomType->images->first(); @endphp
                <article
                    class="group overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm transition hover:shadow-md"
                    data-room-card
                    data-room-slug="{{ $roomType->slug }}"
                    data-room-name="{{ $roomType->translatedName($locale) }}"
                >
                    <div class="aspect-[4/3] w-full overflow-hidden bg-stone-100">
                        @if ($thumbnail)
                            <img
                                src="{{ $thumbnail->image_source }}"
                                alt="{{ $thumbnail->alt_text }}"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                loading="lazy"
                            >
                        @endif
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-stone-900">{{ $roomType->translatedName($locale) }}</h3>
                        <p class="mt-1 line-clamp-2 text-sm text-stone-600">{{ $roomType->translatedDescription($locale) }}</p>

                        <dl class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-stone-500">
                            <div>{{ $roomType->size_sqm }} m²</div>
                            <div>{{ $roomType->maxOccupancy() }} {{ $labels['max_guests'] }}</div>
                            @if ($roomType->breakfast_included)
                                <div>{{ $labels['breakfast_included'] }}</div>
                            @endif
                        </dl>

                        <div class="mt-4 flex items-center justify-between">
                            <p class="text-sm text-stone-500">
                                {{ $labels['from'] }}
                                <span class="text-base font-semibold text-stone-900">
                                    {{ $hotel->currency }} {{ number_format((float) $roomType->base_price, 0, ',', '.') }}
                                </span>
                                {{ $labels['per_night'] }}
                            </p>
                        </div>

                        <button
                            type="button"
                            data-ask-ai-button
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-amber-600 to-amber-400 px-3 py-2 text-sm font-medium text-white transition hover:opacity-90"
                        >
                            <x-ai-mark size="4" class="!rounded !bg-white/20 !shadow-none" />
                            {{ $labels['ask_ai'] }}
                        </button>
                    </div>
                </article>
            @endforeach
        </div>
    </main>

    {{-- Floating AI Concierge widget: collapsed avatar launcher by default,
         opens into the full chat panel with a quick-topic menu. --}}
    <div class="fixed bottom-5 right-5 z-50" data-chat-widget>
        <button
            type="button"
            data-chat-toggle
            aria-label="{{ $labels['chat_heading'] }}"
            class="relative flex items-center justify-center rounded-full shadow-lg ring-1 ring-black/5 transition hover:scale-105"
        >
            <x-concierge-avatar size="16" />
            <span class="absolute -right-0.5 -top-0.5 h-4 w-4 rounded-full border-2 border-white bg-emerald-500"></span>
        </button>

        <div id="concierge-app"
            class="absolute bottom-[4.5rem] right-0 hidden h-[600px] w-[380px] max-w-[calc(100vw-2.5rem)] flex-col overflow-hidden rounded-xl border border-stone-200 bg-white shadow-2xl"
            data-hotel-slug="{{ $hotel->slug }}"
            data-locale="{{ $locale }}"
            data-start-url="{{ route('concierge.start', $hotel->slug) }}"
            data-message-url="{{ route('concierge.message', $hotel->slug) }}"
            data-history-url="{{ route('concierge.history', $hotel->slug) }}"
            data-storage-key="concierge_token_{{ $hotel->slug }}"
            data-label-placeholder="{{ $labels['chat_placeholder'] }}"
            data-label-send="{{ $labels['chat_send'] }}"
            data-label-intro="{{ $labels['chat_intro'] }}"
            data-label-thinking="{{ $labels['thinking'] }}"
            data-label-handed-over="{{ $labels['handed_over'] }}"
            data-label-view-details="{{ $labels['view_details'] }}"
            data-label-book-now="{{ $labels['book_now'] }}"
            data-label-menu-heading="{{ $labels['menu_heading'] }}"
            data-currency="{{ $hotel->currency }}"
        >
            <div class="flex items-center gap-2.5 border-b border-stone-200 bg-gradient-to-r from-amber-50 to-yellow-50 px-4 py-3">
                <x-concierge-avatar size="9" />
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-1.5 font-semibold text-stone-900">
                        {{ $labels['chat_heading'] }}
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    </p>
                    <p class="truncate text-xs text-stone-500">{{ $labels['chat_subtitle'] }}</p>
                </div>
                <button type="button" data-chat-close aria-label="Close" class="shrink-0 rounded-md p-1 text-stone-400 hover:bg-white/60 hover:text-stone-600">
                    <svg viewBox="0 0 20 20" class="h-5 w-5" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </button>
            </div>

            <div data-messages class="flex-1 space-y-3 overflow-y-auto px-4 py-4"></div>

            <div data-status-banner class="hidden border-t border-rose-200 bg-rose-50 px-4 py-2 text-xs text-rose-800"></div>

            <form data-chat-form class="flex items-center gap-2 border-t border-stone-200 p-3">
                <input
                    type="text"
                    data-chat-input
                    placeholder="{{ $labels['chat_placeholder'] }}"
                    autocomplete="off"
                    class="flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none"
                >
                <button
                    type="submit"
                    data-chat-submit
                    class="rounded-lg bg-gradient-to-r from-amber-600 to-amber-400 px-4 py-2 text-sm font-medium text-white transition hover:opacity-90 disabled:opacity-50"
                >{{ $labels['chat_send'] }}</button>
            </form>
        </div>
    </div>

    {{-- Quick-menu button templates, read by JS at boot — kept out of the
         chat log markup so translations stay in one place (server labels). --}}
    <template data-quick-menu-items>
        <button type="button" data-quick-message="{{ $labels['menu_rooms_q'] }}">{{ $labels['menu_rooms'] }}</button>
        <button type="button" data-quick-message="{{ $labels['menu_facilities_q'] }}">{{ $labels['menu_facilities'] }}</button>
        <button type="button" data-quick-message="{{ $labels['menu_policies_q'] }}">{{ $labels['menu_policies'] }}</button>
        <button type="button" data-quick-message="{{ $labels['menu_staff_q'] }}">{{ $labels['menu_staff'] }}</button>
    </template>

</body>
</html>
