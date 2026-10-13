<x-admin-layout title="Activity log">
    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                <tr><th class="px-4 py-3">When</th><th class="px-4 py-3">Who</th><th class="px-4 py-3">What</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-xs text-stone-500">{{ $log->created_at->format('j M Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $log->user?->name ?? ucfirst($log->actor) }}</td>
                        <td class="px-4 py-3">{{ $log->summary }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-stone-400">Nothing has been recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-admin-layout>
