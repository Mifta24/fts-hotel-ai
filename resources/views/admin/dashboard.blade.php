<x-admin-layout title="Dashboard">
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <p class="text-2xl font-semibold text-stone-900">{{ $stats['room_types'] }}</p>
            <p class="text-xs text-stone-500">Room types</p>
        </div>
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <p class="text-2xl font-semibold text-stone-900">{{ $stats['knowledge_items'] }}</p>
            <p class="text-xs text-stone-500">Knowledge items</p>
        </div>
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <p class="text-2xl font-semibold text-stone-900">{{ $stats['pending_bookings'] }}</p>
            <p class="text-xs text-stone-500">Pending bookings</p>
        </div>
        <div class="rounded-xl border {{ $stats['open_handovers'] > 0 ? 'border-amber-300 bg-amber-50' : 'border-stone-200 bg-white' }} p-4">
            <p class="text-2xl font-semibold text-stone-900">{{ $stats['open_handovers'] }}</p>
            <p class="text-xs text-stone-500">Open handovers</p>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-stone-200 bg-white">
            <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
                <p class="font-medium text-stone-900">Open handovers</p>
                <a href="{{ route('admin.handovers.index') }}" class="text-xs text-stone-500 hover:underline">View all</a>
            </div>
            <div class="divide-y divide-stone-100">
                @forelse ($openHandovers as $handover)
                    <a href="{{ route('admin.handovers.show', $handover) }}" class="block px-4 py-3 hover:bg-stone-50">
                        <p class="text-sm font-medium text-stone-900">{{ ucwords(str_replace('_', ' ', $handover->reason)) }}</p>
                        <p class="mt-0.5 line-clamp-1 text-xs text-stone-500">{{ $handover->summary }}</p>
                    </a>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-stone-400">No open handovers.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-stone-200 bg-white">
            <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
                <p class="font-medium text-stone-900">Recent bookings</p>
                <a href="{{ route('admin.bookings.index') }}" class="text-xs text-stone-500 hover:underline">View all</a>
            </div>
            <div class="divide-y divide-stone-100">
                @forelse ($recentBookings as $booking)
                    <div class="px-4 py-3">
                        <p class="text-sm font-medium text-stone-900">{{ $booking->guest_name }} — {{ $booking->roomType->name }}</p>
                        <p class="mt-0.5 text-xs text-stone-500">{{ $booking->check_in->toFormattedDateString() }} → {{ $booking->check_out->toFormattedDateString() }} · {{ ucfirst($booking->status) }}</p>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-stone-400">No bookings yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-admin-layout>
