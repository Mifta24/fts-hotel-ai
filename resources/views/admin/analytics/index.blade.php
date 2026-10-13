<x-admin-layout title="Concierge analytics">
    @php
        $localeNames = ['id' => 'Indonesian', 'en' => 'English', 'ja' => 'Japanese'];
    @endphp

    <div class="mb-4 flex gap-2 text-sm">
        @foreach ($periods as $period)
            <a href="{{ route('admin.analytics.index', ['days' => $period]) }}" class="rounded-full px-3 py-1 {{ $report['days'] === $period ? 'bg-stone-900 text-white' : 'border border-stone-300 bg-white text-stone-600' }}">Last {{ $period }} days</a>
        @endforeach
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['Chats started', $report['conversations'], $report['engaged'].' with a guest message'],
            ['Guest messages', $report['guest_messages'], null],
            ['Chats that became a booking', $report['conversion'] === null ? '–' : $report['conversion'].'%', $report['bookings_from_chat'].' of '.$report['bookings_total'].' bookings came from chat'],
            ['Handed to staff', $report['handovers'], null],
        ] as [$label, $value, $hint])
            <div class="rounded-xl border border-stone-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-stone-400">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold text-stone-900">{{ $value }}</p>
                @if ($hint)<p class="mt-1 text-xs text-stone-400">{{ $hint }}</p>@endif
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="text-sm font-medium text-stone-900">Chats by language</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($report['by_locale'] as $locale => $total)
                    <li class="flex justify-between"><span>{{ $localeNames[$locale] ?? $locale }}</span><span class="text-stone-500">{{ $total }}</span></li>
                @empty
                    <li class="text-stone-400">No chats yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="text-sm font-medium text-stone-900">Answers guests rated</h2>
            <p class="mt-3 text-sm">👍 {{ $report['ratings']['up'] }} &nbsp; 👎 {{ $report['ratings']['down'] }}</p>
            <ul class="mt-3 space-y-3 text-sm">
                @foreach ($report['disliked'] as $item)
                    <li class="rounded-lg bg-red-50 px-3 py-2">
                        <p class="text-xs text-stone-500">Guest: {{ $item['question'] ?? '—' }}</p>
                        <p class="mt-1 text-stone-800">{{ $item['reply'] }}</p>
                        <p class="mt-1 text-xs text-stone-400">{{ $item['at'] }}</p>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="text-sm font-medium text-stone-900">What guests look up</h2>
            <p class="text-xs text-stone-400">Knowledge searches the concierge ran.</p>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($report['topics'] as $row)
                    <li class="flex justify-between"><span>{{ $row['text'] }}</span><span class="text-stone-500">{{ $row['count'] }}</span></li>
                @empty
                    <li class="text-stone-400">Nothing yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <h2 class="text-sm font-medium text-amber-900">Gaps in the knowledge base</h2>
            <p class="text-xs text-amber-700">Searches that found nothing. Add an entry for each.</p>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($report['gaps'] as $row)
                    <li class="flex justify-between"><span>{{ $row['text'] }}</span><span class="text-amber-700">{{ $row['count'] }}×</span></li>
                @empty
                    <li class="text-amber-700">No misses. Nice.</li>
                @endforelse
            </ul>
            @foreach ($report['unanswered_handovers'] as $summary)
                <p class="mt-3 border-t border-amber-200 pt-2 text-xs text-amber-800">Handed over for low confidence: {{ $summary }}</p>
            @endforeach
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="text-sm font-medium text-stone-900">Questions asked more than once</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($report['repeated_questions'] as $row)
                    <li class="flex justify-between gap-3"><span>{{ $row['text'] }}</span><span class="shrink-0 text-stone-500">{{ $row['count'] }}×</span></li>
                @empty
                    <li class="text-stone-400">No repeated wording yet.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="text-sm font-medium text-stone-900">What the concierge does</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($report['tools'] as $row)
                    <li class="flex justify-between"><span class="font-mono text-xs">{{ $row['text'] }}</span><span class="text-stone-500">{{ $row['count'] }}</span></li>
                @empty
                    <li class="text-stone-400">Nothing yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-admin-layout>
