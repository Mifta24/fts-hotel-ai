<x-member-layout :hotel="$hotel" :locale="$locale" :labels="$labels" :title="$labels['account_title']">
    <div class="mt-4 flex items-center justify-between gap-3">
        <p class="text-sm font-medium">{{ \App\Core\Member\Support\MemberLabels::fill($labels['hello'], ['name' => $member->displayName()]) }}</p>
        <form method="POST" action="{{ route('member.logout', ['hotelSlug' => $hotel->slug]) }}">
            @csrf
            <input type="hidden" name="lang" value="{{ $locale }}">
            <button type="submit" class="text-sm text-stone-500 underline hover:text-stone-900">{{ $labels['logout'] }}</button>
        </form>
    </div>

    @if ($member->needsProfile())
        <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ $labels['welcome'] }}</p>
    @endif

    @if ($program)
        <section class="mt-6 rounded-xl border border-stone-200 bg-white p-4 text-sm">
            <h2 class="font-semibold">{{ $labels['loyalty_title'] }}</h2>
            <div class="mt-3 flex items-end justify-between gap-4">
                <div>
                    <p class="text-xs text-stone-500">{{ $labels['tier'] }}</p>
                    <p class="text-lg font-semibold">{{ $tier?->name ?? $labels['no_tier'] }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-stone-500">{{ $labels['your_points'] }}</p>
                    <p class="text-lg font-semibold">{{ number_format($points, 0, ',', '.') }}</p>
                </div>
            </div>
            @if ($tier && $tier->discountPercent > 0)
                <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-emerald-800">{{ \App\Core\Member\Support\MemberLabels::fill($labels['discount_benefit'], ['percent' => rtrim(rtrim(number_format($tier->discountPercent, 2, '.', ''), '0'), '.')]) }}</p>
            @endif
            @if ($nextTier)
                <p class="mt-3 text-stone-600">{{ \App\Core\Member\Support\MemberLabels::fill($labels['next_tier'], ['points' => number_format($nextTier->minPoints - $points, 0, ',', '.'), 'tier' => $nextTier->name]) }}</p>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $nextTier->minPoints }}" aria-valuenow="{{ $points }}">
                    <div class="h-full bg-stone-900" style="width: {{ min(100, (int) round($points / max(1, $nextTier->minPoints) * 100)) }}%"></div>
                </div>
            @elseif ($tier)
                <p class="mt-3 text-stone-600">{{ $labels['top_tier'] }}</p>
            @endif
            <p class="mt-3 text-xs text-stone-400">{{ \App\Core\Member\Support\MemberLabels::fill($labels['earn_rule'], ['spend' => $hotel->currency.' '.number_format($program->spendPerPoint, 0, ',', '.')]) }}</p>

            <h3 class="mt-4 font-medium">{{ $labels['points_history'] }}</h3>
            <ul class="mt-2 divide-y divide-stone-100">
                @forelse ($pointsHistory as $line)
                    <li class="flex justify-between gap-3 py-2"><span class="text-stone-600">{{ $line->note ?: ucfirst($line->type) }} <span class="text-xs text-stone-400">{{ $line->created_at->toDateString() }}</span></span><span @class(['font-medium', 'text-emerald-700' => $line->points > 0, 'text-red-700' => $line->points < 0])>{{ $line->points > 0 ? '+' : '' }}{{ number_format($line->points, 0, ',', '.') }}</span></li>
                @empty
                    <li class="py-2 text-stone-400">{{ $labels['no_points'] }}</li>
                @endforelse
            </ul>
        </section>
    @endif

    <section class="mt-6 rounded-xl border border-stone-200 bg-white p-4 text-sm">
        <h2 class="font-semibold">{{ $labels['profile'] }}</h2>
        <form method="POST" action="{{ route('member.profile', ['hotelSlug' => $hotel->slug]) }}" class="mt-3 space-y-3">
            @csrf @method('PUT')
            <p class="text-stone-500">{{ $member->email }}</p>
            <label class="block font-medium">{{ $labels['name'] }}
                <input name="name" value="{{ old('name', $member->name) }}" required maxlength="100" autocomplete="name" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-normal">
            </label>
            <label class="block font-medium">{{ $labels['phone'] }}
                <input name="phone" value="{{ old('phone', $member->phone) }}" maxlength="20" autocomplete="tel" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-normal">
            </label>
            <label class="block font-medium">{{ $labels['language'] }}
                <select name="locale" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 font-normal">
                    @foreach (['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'] as $code => $name)<option value="{{ $code }}" @selected(old('locale', $locale) === $code)>{{ $name }}</option>@endforeach
                </select>
            </label>
            <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700">{{ $labels['save'] }}</button>
        </form>
    </section>

    <section class="mt-6">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">{{ $labels['bookings'] }}</h2>
            <a href="{{ route('hotel.reservation', ['hotelSlug' => $hotel->slug, 'lang' => $locale]) }}" class="text-sm text-stone-600 underline hover:text-stone-900">{{ $labels['book_now'] }}</a>
        </div>

        @forelse ($bookings as $booking)
            <article id="booking-{{ $booking->reference }}" class="mt-3 rounded-xl border border-stone-200 bg-white p-4 text-sm">
                <p class="font-mono text-xs text-stone-500">{{ $booking->reference }}</p>
                <dl class="mt-3 space-y-2">
                    <div class="flex justify-between gap-4"><dt class="text-stone-500">{{ $labels['room'] }}</dt><dd class="text-right">{{ $booking->room_count }} × {{ $booking->roomType->name }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-stone-500">{{ $labels['stay'] }}</dt><dd class="text-right">{{ $booking->check_in->toDateString() }} → {{ $booking->check_out->toDateString() }} ({{ $booking->nights() }} {{ $labels['nights'] }})</dd></div>
                    @if ((float) $booking->discount_percent > 0)
                        <div class="flex justify-between gap-4"><dt class="text-stone-500">{{ $labels['member_discount'] }}</dt><dd>{{ rtrim(rtrim(number_format((float) $booking->discount_percent, 2, '.', ''), '0'), '.') }}% (−{{ $hotel->currency }} {{ number_format((float) $booking->discount_amount, 0, ',', '.') }})</dd></div>
                    @endif
                    <div class="flex justify-between gap-4"><dt class="text-stone-500">{{ $labels['total'] }}</dt><dd>{{ $hotel->currency }} {{ number_format((float) $booking->total_price, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-stone-500">{{ $labels['status'] }}</dt><dd>{{ $labels[$booking->status] ?? $booking->status }}</dd></div>
                </dl>

                @if ($booking->changeable)
                    <details class="mt-4 border-t border-stone-100 pt-3">
                        <summary class="cursor-pointer font-medium">{{ $labels['new_dates'] }}</summary>
                        <form method="POST" action="{{ route('member.bookings.reschedule', ['hotelSlug' => $hotel->slug, 'reference' => $booking->reference]) }}" class="mt-3 grid grid-cols-2 gap-3">
                            @csrf
                            <input type="hidden" name="lang" value="{{ $locale }}">
                            <label class="block">{{ $labels['check_in'] }}<input type="date" name="check_in" required min="{{ now($hotel->timezone)->toDateString() }}" class="mt-1 w-full rounded-lg border border-stone-300 px-2 py-2"></label>
                            <label class="block">{{ $labels['check_out'] }}<input type="date" name="check_out" required class="mt-1 w-full rounded-lg border border-stone-300 px-2 py-2"></label>
                            <button type="submit" class="col-span-2 rounded-lg bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700">{{ $labels['reschedule'] }}</button>
                        </form>
                    </details>
                    <form method="POST" action="{{ route('member.bookings.cancel', ['hotelSlug' => $hotel->slug, 'reference' => $booking->reference]) }}" class="mt-3" onsubmit="return confirm(@js($labels['confirm_cancel']))">
                        @csrf
                        <input type="hidden" name="lang" value="{{ $locale }}">
                        <button type="submit" class="w-full rounded-lg border border-red-300 px-4 py-2 font-medium text-red-700 hover:bg-red-50">{{ $labels['cancel'] }}</button>
                    </form>
                @endif
            </article>
        @empty
            <p class="mt-3 rounded-xl border border-dashed border-stone-300 px-4 py-6 text-center text-sm text-stone-500">{{ $labels['no_bookings'] }}</p>
        @endforelse
    </section>
</x-member-layout>
