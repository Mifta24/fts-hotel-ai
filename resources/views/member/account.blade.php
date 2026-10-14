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
