<x-admin-layout title="Bookings">
    <div class="mb-4 flex gap-2 text-sm">
        @foreach (['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled'] as $value => $label)
            <a href="{{ route('admin.bookings.index', $value ? ['status' => $value] : []) }}"
                class="rounded-full px-3 py-1 {{ $status === $value || (! $status && $value === '') ? 'bg-stone-900 text-white' : 'bg-white border border-stone-300 text-stone-600' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">Guest</th>
                    <th class="px-4 py-3">Room</th>
                    <th class="px-4 py-3">Dates</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($bookings as $booking)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium text-stone-900">{{ $booking->guest_name }}</p>
                            <p class="text-xs text-stone-500">{{ $booking->guest_phone }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $booking->roomType->name }}</td>
                        <td class="px-4 py-3 text-xs text-stone-500">{{ $booking->check_in->toFormattedDateString() }} → {{ $booking->check_out->toFormattedDateString() }}</td>
                        <td class="px-4 py-3">{{ $hotel->currency }} {{ number_format((float) $booking->total_price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs',
                                'bg-amber-100 text-amber-700' => $booking->status === 'pending',
                                'bg-emerald-100 text-emerald-700' => $booking->status === 'confirmed',
                                'bg-stone-100 text-stone-500' => $booking->status === 'cancelled',
                            ])>{{ ucfirst($booking->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($booking->status === 'pending')
                                <form method="POST" action="{{ route('admin.bookings.status', $booking) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="confirmed">
                                    <button type="submit" class="text-emerald-600 hover:underline">Confirm</button>
                                </form>
                                <form method="POST" action="{{ route('admin.bookings.status', $booking) }}" class="inline" onsubmit="return confirm('Cancel this booking?')">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="ml-3 text-red-600 hover:underline">Cancel</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-stone-400">No bookings found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $bookings->links() }}</div>
</x-admin-layout>
