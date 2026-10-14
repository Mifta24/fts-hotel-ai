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

            @if ($program)
                <div class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                    <div class="flex items-end justify-between">
                        <div><p class="text-xs text-stone-400">Tier</p><p class="text-lg font-semibold">{{ $program->tierFor($points)?->name ?? '—' }}</p></div>
                        <div class="text-right"><p class="text-xs text-stone-400">Points</p><p class="text-lg font-semibold">{{ number_format($points, 0, ',', '.') }}</p></div>
                    </div>
                    <form method="POST" action="{{ route('admin.members.points', $member->id) }}" class="mt-4 grid grid-cols-[110px_1fr_auto] gap-2">
                        @csrf
                        <input type="number" name="points" required placeholder="+100 / -50" aria-label="Points to add or remove" class="rounded-lg border border-stone-300 px-3 py-2">
                        <input name="note" required maxlength="200" placeholder="Why? e.g. Birthday gift" aria-label="Reason" class="rounded-lg border border-stone-300 px-3 py-2">
                        <button type="submit" class="rounded-lg bg-stone-900 px-3 py-2 font-medium text-white hover:bg-stone-700">Adjust</button>
                    </form>
                    @error('points')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('note')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <ul class="mt-4 divide-y divide-stone-100">
                        @forelse ($pointsHistory as $line)
                            <li class="flex justify-between gap-3 py-2"><span class="text-stone-600">{{ $line->note ?: ucfirst($line->type) }}@if ($line->actor_name) <span class="text-xs text-stone-400">— {{ $line->actor_name }}</span>@endif <span class="text-xs text-stone-400">{{ $line->created_at->format('j M Y') }}</span></span><span class="font-medium">{{ $line->points > 0 ? '+' : '' }}{{ number_format($line->points, 0, ',', '.') }}</span></li>
                        @empty
                            <li class="py-2 text-stone-400">No points yet.</li>
                        @endforelse
                    </ul>
                </div>
            @endif

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
