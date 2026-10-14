<x-admin-layout title="Members">
    <form method="GET" action="{{ route('admin.members.index') }}" class="mb-4 flex gap-2 text-sm">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search name or email" aria-label="Search members" class="w-64 rounded-lg border border-stone-300 px-3 py-2">
        <button type="submit" class="rounded-lg bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700">Search</button>
        @if ($search !== '')<a href="{{ route('admin.members.index') }}" class="px-2 py-2 text-stone-500 hover:underline">Clear</a>@endif
    </form>

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                <tr><th class="px-4 py-3">Member</th><th class="px-4 py-3">Phone</th>@if ($program)<th class="px-4 py-3">Tier</th><th class="px-4 py-3">Points</th>@endif<th class="px-4 py-3">Reservations</th><th class="px-4 py-3">Joined</th><th class="px-4 py-3">Last sign-in</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($members as $member)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.members.show', $member->id) }}" class="font-medium text-stone-900 underline decoration-stone-300 hover:decoration-stone-900">{{ $member->name ?: '—' }}</a>
                            <p class="text-xs text-stone-500">{{ $member->email }}</p>
                        </td>
                        <td class="px-4 py-3 text-xs text-stone-600">{{ $member->phone ?: '—' }}</td>
                        @if ($program)
                            @php $balance = (int) ($balances[$member->id] ?? 0); @endphp
                            <td class="px-4 py-3 text-xs">{{ $program->tierFor($balance)?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ number_format($balance, 0, ',', '.') }}</td>
                        @endif
                        <td class="px-4 py-3">{{ $bookingCounts[$member->id] ?? 0 }}</td>
                        <td class="px-4 py-3 text-xs text-stone-500">{{ $member->created_at->format('j M Y') }}</td>
                        <td class="px-4 py-3 text-xs text-stone-500">{{ $member->last_login_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $program ? 7 : 5 }}" class="px-4 py-8 text-center text-stone-400">{{ $search !== '' ? 'No member matches that search.' : 'No members yet. Guests join from the hotel page.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $members->links() }}</div>
</x-admin-layout>
