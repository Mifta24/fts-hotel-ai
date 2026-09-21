        <div id="concierge-app"
            class="stage-chat"
            data-inline="true"
            data-label-connection-error="{{ $lobby['connection_error'] }}"
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
            data-label-staff="{{ $labels['menu_staff'] }}"
            data-label-error="{{ $labels['chat_error'] }}"
            data-label-slow="{{ $labels['chat_slow'] }}"
            data-label-retry="{{ $labels['chat_retry'] }}"
            data-currency="{{ $hotel->currency }}"
        >
            <section class="chat-log" aria-label="{{ $labels['chat_heading'] }}">
                <header class="chat-log-header">
                    <span class="chat-host-avatar" aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-1.5 font-semibold text-stone-900">
                            {{ $labels['chat_heading'] }}
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        </p>
                        <p class="truncate text-xs text-stone-500">{{ $labels['chat_subtitle'] }}</p>
                    </div>
                    <button type="button" data-chat-close class="chat-log-close" aria-label="{{ $lobby['back'] }}"><span aria-hidden="true">×</span></button>
                </header>

                <div data-messages role="log" aria-live="polite" aria-label="{{ $labels['chat_heading'] }}" class="chat-log-messages space-y-3"></div>

                <div data-status-banner class="hidden border-t border-rose-200 bg-rose-50 px-4 py-2 text-xs text-rose-800"></div>
            </section>

            <form data-chat-form class="chat-bar">
                <input
                    type="text"
                    data-chat-input
                    aria-label="{{ $labels['chat_placeholder'] }}"
                    maxlength="4000"
                    placeholder="{{ $labels['chat_placeholder'] }}"
                    autocomplete="off"
                >
                <button type="submit" data-chat-submit>{{ $labels['chat_send'] }}</button>
            </form>

            <ul class="chat-chips" aria-label="{{ $labels['menu_heading'] }}">
                @foreach ([[$labels['menu_rooms'], $labels['menu_rooms_q']], [$labels['menu_facilities'], $labels['menu_facilities_q']], [$labels['menu_policies'], $labels['menu_policies_q']], [$lobby['start_booking'], $lobby['reservation_q']], [$labels['menu_staff'], $labels['menu_staff_q']]] as [$chip, $question])
                    <li><button type="button" data-hero-quick-message="{{ $question }}">{{ $chip }} <span aria-hidden="true">›</span></button></li>
                @endforeach
            </ul>
        </div>
