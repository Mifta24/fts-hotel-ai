<section class="lobby-content stage-panel-right @container" aria-label="{{ $wizard['title'] }}">
    <a href="{{ route('hotel.show', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_lobby'] }}" class="panel-close" aria-label="{{ $lobby['back'] }}"><span aria-hidden="true">×</span></a>
    <p class="lobby-eyebrow">{{ $hotel->name }}</p>
    <h2>{{ $wizard['title'] }}</h2>

    <div class="wizard" data-wizard
        data-quote-url="{{ route('reservation.quote', $hotel->slug) }}"
        data-submit-url="{{ route('reservation.store', $hotel->slug) }}"
        data-hotel-slug="{{ $hotel->slug }}"
        data-locale="{{ $locale }}"
        data-currency="{{ $hotel->currency }}"
        data-today="{{ $today }}"
        data-max-nights="{{ \App\Services\Reservation\ReservationService::MAX_NIGHTS }}"
        data-preselect-room="{{ $preselectedRoom ?? '' }}"
    >
        <div data-wizard-flow>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-stone-600">{{ $wizard['intro'] }}</p>

            <div class="wizard-progress" aria-label="{{ $wizard['title'] }}">
                <p class="wizard-step-label" data-wizard-step-label aria-live="polite"></p>
                <ol>
                    @foreach ($wizard['steps'] as $stepLabel)
                        <li data-progress-step><span>{{ $stepLabel }}</span></li>
                    @endforeach
                </ol>
            </div>

            <form data-wizard-form novalidate autocomplete="on">
                <div data-step="1" class="wizard-step">
                    <label class="wizard-field">
                        <span>{{ $wizard['check_in'] }}</span>
                        <input type="date" name="check_in" min="{{ $today }}" required>
                    </label>
                    <label class="wizard-field">
                        <span>{{ $wizard['check_out'] }}</span>
                        <input type="date" name="check_out" min="{{ $today }}" required>
                    </label>
                    <p class="wizard-hint" data-nights-hint aria-live="polite"></p>
                </div>

                <div data-step="2" class="wizard-step" hidden>
                    <label class="wizard-field">
                        <span>{{ $wizard['adults'] }}</span>
                        <input type="number" name="adults" min="1" max="20" value="2" inputmode="numeric" required>
                    </label>
                    <label class="wizard-field">
                        <span>{{ $wizard['children'] }}</span>
                        <input type="number" name="children" min="0" max="10" value="0" inputmode="numeric">
                    </label>
                    <label class="wizard-field">
                        <span>{{ $wizard['rooms'] }}</span>
                        <input type="number" name="rooms" min="1" max="10" value="1" inputmode="numeric" required>
                    </label>
                </div>

                <div data-step="3" class="wizard-step wizard-step-wide" hidden>
                    <fieldset>
                        <legend>{{ $wizard['choose_room'] }}</legend>
                        <div class="wizard-rooms">
                            @foreach ($roomTypes as $roomType)
                                <label class="wizard-room" data-room-option
                                    data-name="{{ $roomType->translatedName($locale) }}"
                                    data-max-adults="{{ $roomType->max_adults }}"
                                    data-max-occupancy="{{ $roomType->maxOccupancy() }}"
                                    data-extra-bed="{{ $roomType->extra_bed_available ? 1 : 0 }}"
                                >
                                    <input type="radio" name="room_type_slug" value="{{ $roomType->slug }}">
                                    <span class="wizard-room-body">
                                        <strong>{{ $roomType->translatedName($locale) }}</strong>
                                        <span>{{ str_replace(':count', (string) $roomType->maxOccupancy(), $wizard['fits']) }}</span>
                                        <span class="wizard-room-price">{{ $labels['from'] }} {{ $hotel->currency }} {{ number_format((float) $roomType->base_price, 0, ',', '.') }} {{ $labels['per_night'] }}</span>
                                        <span class="wizard-room-hint" data-room-hint></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="wizard-check" data-extra-bed-field hidden>
                        <input type="checkbox" name="extra_bed" value="1">
                        <span>{{ $wizard['extra_bed'] }}</span>
                    </label>
                </div>

                <div data-step="4" class="wizard-step" hidden>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['name'] }}</span>
                        <input type="text" name="guest_name" maxlength="100" autocomplete="name" required>
                    </label>
                    <fieldset class="wizard-field-wide">
                        <legend>{{ $wizard['contact_method'] }}</legend>
                        <div class="wizard-pills">
                            @foreach (['whatsapp', 'phone', 'email'] as $method)
                                <label><input type="radio" name="contact_type" value="{{ $method }}" @checked($method === 'whatsapp')><span>{{ $wizard[$method] }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['contact_value'] }}</span>
                        <input type="text" name="contact_value" maxlength="120" autocomplete="tel" required>
                    </label>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['special'] }}</span>
                        <textarea name="special_request" rows="2" maxlength="500" placeholder="{{ $wizard['special_placeholder'] }}"></textarea>
                    </label>
                </div>

                <div data-step="5" class="wizard-step wizard-step-wide" hidden>
                    <dl class="wizard-summary" data-summary></dl>
                    <p class="wizard-total"><span>{{ $wizard['estimated_total'] }}</span> <strong data-summary-total></strong></p>
                    <p class="wizard-hint">{{ $wizard['disclaimer'] }}</p>
                </div>

                <div class="wizard-error" data-wizard-error role="alert" hidden>
                    <p data-wizard-error-text></p>
                    <div class="wizard-alternatives" data-wizard-alternatives hidden>
                        <p>{{ $wizard['available_instead'] }}</p>
                        <ul></ul>
                    </div>
                </div>

                <div class="wizard-actions">
                    <button type="button" class="wizard-secondary" data-wizard-back hidden>{{ $wizard['back'] }}</button>
                    <button type="button" class="lobby-action" data-wizard-next>{{ $wizard['next'] }} <span aria-hidden="true">→</span></button>
                    <button type="submit" class="lobby-action" data-wizard-submit hidden>{{ $wizard['submit'] }}</button>
                </div>
            </form>
        </div>

        <div class="wizard-done" data-wizard-done hidden>
            <p class="lobby-eyebrow">{{ $wizard['done_title'] }}</p>
            <p class="wizard-reference-label">{{ $wizard['reference'] }}</p>
            <p class="wizard-reference" data-done-reference></p>
            <p class="wizard-status">{{ $wizard['awaiting'] }}</p>
            <p class="mt-4 max-w-md text-sm leading-relaxed text-stone-600">{{ $wizard['done_hint'] }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a class="lobby-action" data-done-whatsapp target="_blank" rel="noopener" hidden>{{ $wizard['send_whatsapp'] }} <span aria-hidden="true">↗</span></a>
                <a class="wizard-secondary" data-done-phone hidden>{{ $wizard['call_hotel'] }}</a>
                <a class="wizard-secondary" data-done-email hidden>{{ $wizard['email_hotel'] }}</a>
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('hotel.show', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" data-stage-exit class="wizard-secondary">{{ $lobby['back'] }}</a>
                <button type="button" class="wizard-secondary" data-wizard-reset>{{ $wizard['new_request'] }}</button>
            </div>
        </div>

        <script type="application/json" data-wizard-labels>@json($wizard)</script>
    </div>
</section>
