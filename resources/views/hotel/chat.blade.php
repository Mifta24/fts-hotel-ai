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
            data-label-open="{{ $labels['chat_open'] }}"
            data-label-close="{{ $labels['chat_close'] }}"
            data-label-intro="{{ $labels['chat_intro'] }}"
            data-label-breakfast-included="{{ $labels['breakfast_included'] }}"
            data-label-max-guests="{{ $labels['max_guests'] }}"
            data-label-room-only="{{ $labels['chat_room_only'] }}"
            data-label-night="{{ $labels['chat_night'] }}"
            data-label-no-availability="{{ $labels['chat_no_availability'] }}"
            data-label-booking-received="{{ $labels['chat_booking_received'] }}"
            data-label-reference="{{ $labels['chat_reference'] }}"
            data-label-view-suffix="{{ $labels['chat_view_suffix'] }}"
            data-label-thinking="{{ $labels['thinking'] }}"
            data-label-handed-over="{{ $labels['handed_over'] }}"
            data-label-status-sent="{{ $labels['chat_status_sent'] }}"
            data-label-status-waiting="{{ $labels['chat_status_waiting'] }}"
            data-label-status-replied="{{ $labels['chat_status_replied'] }}"
            data-label-draft-title="{{ $labels['chat_draft_title'] }}"
            data-label-draft-body="{{ $labels['chat_draft_body'] }}"
            data-label-draft-keep="{{ $labels['chat_draft_keep'] }}"
            data-label-draft-discard="{{ $labels['chat_draft_discard'] }}"
            data-label-view-details="{{ $labels['view_details'] }}"
            data-label-book-now="{{ $labels['book_now'] }}"
            data-label-menu-heading="{{ $labels['menu_heading'] }}"
            data-label-staff="{{ $labels['menu_staff'] }}"
            data-label-error="{{ $labels['chat_error'] }}"
            data-label-slow="{{ $labels['chat_slow'] }}"
            data-label-retry="{{ $labels['chat_retry'] }}"
            data-currency="{{ $hotel->currency }}"
            data-lobby-url="{{ route('hotel.show', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}"
            data-room-url="{{ route('hotel.room', ['hotelSlug' => $hotel->slug, 'roomSlug' => '__SLUG__', 'lang' => $locale]) }}"
            data-staff-url="{{ route('hotel.staff', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}"
            data-reservation-url="{{ route('hotel.reservation', ['hotelSlug' => $hotel->slug, 'lang' => $locale, 'room' => '__SLUG__']) }}"
        >
            <section id="concierge-chat-log" class="chat-log" aria-label="{{ $labels['chat_heading'] }}" aria-hidden="true">
                <header class="chat-log-header">
                    <span class="chat-host-avatar" aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-1.5 font-semibold text-stone-900">
                            {{ $labels['chat_heading'] }}
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        </p>
                        <p class="truncate text-xs text-stone-500">{{ $labels['chat_subtitle'] }}</p>
                        <p data-chat-status class="chat-status" aria-live="polite" hidden></p>
                    </div>
                    <button type="button" data-chat-close class="chat-log-close" aria-label="{{ $labels['chat_close'] }}"><span aria-hidden="true">×</span></button>
                </header>

                <div data-messages role="log" aria-live="polite" aria-label="{{ $labels['chat_heading'] }}" class="chat-log-messages space-y-3"></div>

                <div data-thinking-indicator class="chat-thinking" role="status" hidden>
                    <span class="chat-host-avatar" aria-hidden="true"></span>
                    <span class="chat-thinking-bubble">
                        <span class="chat-thinking-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                        <span>{{ $labels['thinking'] }}</span>
                    </span>
                </div>

                <div data-status-banner class="hidden border-t border-rose-200 bg-rose-50 px-4 py-2 text-xs text-rose-800"></div>
            </section>

            <form data-chat-form data-chat-composer class="chat-bar" aria-hidden="true">
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

            <div data-draft-dialog class="chat-draft-dialog" hidden aria-hidden="true" role="dialog" aria-modal="false" aria-labelledby="chat-draft-title">
                <p id="chat-draft-title" data-draft-title class="font-semibold text-stone-900">{{ $labels['chat_draft_title'] }}</p>
                <p data-draft-body class="mt-1 text-xs leading-relaxed text-stone-600">{{ $labels['chat_draft_body'] }}</p>
                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" data-draft-keep class="rounded-full border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700">{{ $labels['chat_draft_keep'] }}</button>
                    <button type="button" data-draft-discard class="rounded-full bg-stone-900 px-3 py-1.5 text-xs font-medium text-white">{{ $labels['chat_draft_discard'] }}</button>
                </div>
            </div>

            <button type="button" data-chat-launcher class="chat-launcher" aria-controls="concierge-chat-log" aria-expanded="false">
                <span class="chat-launcher-dot" aria-hidden="true"></span>
                <span>{{ $labels['chat_open'] }}</span>
                <span class="chat-launcher-arrow" aria-hidden="true">↗</span>
            </button>

            <ul class="chat-chips" aria-label="{{ $labels['menu_heading'] }}">
                @foreach ([[$labels['menu_rooms'], $labels['menu_rooms_q']], [$labels['menu_facilities'], $labels['menu_facilities_q']], [$labels['menu_policies'], $labels['menu_policies_q']], [$lobby['start_booking'], $lobby['reservation_q']], [$labels['menu_staff'], $labels['menu_staff_q']]] as [$chip, $question])
                    <li><button type="button" data-hero-quick-message="{{ $question }}">{{ $chip }} <span aria-hidden="true">›</span></button></li>
                @endforeach
            </ul>
        </div>
