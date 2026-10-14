<x-admin-layout :title="$member->name ?: $member->email">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_340px]">
        <div class="space-y-4">
            <div class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                <dl class="grid grid-cols-2 gap-3">
                    <div><dt class="text-xs text-stone-400">Name</dt><dd>{{ $member->name ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-stone-400">Email</dt><dd>{{ $member->email }}</dd></div>
                    <div><dt class="text-xs text-stone-400">Phone</dt><dd>{{ $member->phone ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-stone-400">Language</dt><dd>{{ strtoupper($member->locale ?? $hotel->default_locale) }}</dd></div>
                    <div><dt class="text-xs text-stone-400">Joined</dt><dd>{{ $member->created_at->format('j M Y') }}</dd></div>
                    <div><dt class="text-xs text-stone-400">Last sign-in</dt><dd>{{ $member->last_login_at?->diffForHumans() ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
                <p class="border-b border-stone-200 px-4 py-3 text-sm font-medium text-stone-900">Reservations</p>
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($bookings as $booking)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs">
                                    @if (auth()->user()->canInHotel('bookings.view'))<a href="{{ route('admin.bookings.show', $booking) }}" class="underline decoration-stone-300 hover:decoration-stone-900">{{ $booking->reference }}</a>@else{{ $booking->reference }}@endif
                                </td>
                                <td class="px-4 py-3">{{ $booking->room_count }} × {{ $booking->roomType->name }}</td>
                                <td class="px-4 py-3 text-xs text-stone-500">{{ $booking->check_in->toFormattedDateString() }} → {{ $booking->check_out->toFormattedDateString() }}</td>
                                <td class="px-4 py-3">{{ $hotel->currency }} {{ number_format((float) $booking->total_price, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-xs text-stone-500">{{ ucfirst($booking->status) }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-6 text-center text-stone-400">No reservations yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-admin.notes :subject="$member" type="member" />
    </div>
</x-admin-layout>
