        <div id="concierge-app"
            class="lobby-chat"
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
            data-currency="{{ $hotel->currency }}"
        >
            <div class="flex items-center gap-2.5 border-b border-stone-200 bg-gradient-to-r from-amber-50 to-yellow-50 px-4 py-3">
                <span class="chat-host-avatar" aria-hidden="true"></span>
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-1.5 font-semibold text-stone-900">
                        {{ $labels['chat_heading'] }}
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    </p>
                    <p class="truncate text-xs text-stone-500">{{ $labels['chat_subtitle'] }}</p>
                </div>

            </div>

            <div data-messages role="log" aria-live="polite" aria-label="{{ $labels['chat_heading'] }}" class="flex-1 space-y-3 overflow-y-auto px-4 py-4"></div>

            <div data-status-banner class="hidden border-t border-rose-200 bg-rose-50 px-4 py-2 text-xs text-rose-800"></div>

            <form data-chat-form class="flex items-center gap-2 border-t border-stone-200 p-3">
                <input
                    type="text"
                    data-chat-input
                    aria-label="{{ $labels['chat_placeholder'] }}"
                    maxlength="4000"
                    placeholder="{{ $labels['chat_placeholder'] }}"
                    autocomplete="off"
                    class="min-w-0 flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none"
                >
                <button
                    type="submit"
                    data-chat-submit
                    class="rounded-lg bg-gradient-to-r from-amber-600 to-amber-400 px-4 py-2 text-sm font-medium text-white transition hover:opacity-90 disabled:opacity-50"
                >{{ $labels['chat_send'] }}</button>
            </form>
        </div>
