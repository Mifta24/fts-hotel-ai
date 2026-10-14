<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentHotel;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookingController extends Controller
{
    use ResolvesCurrentHotel;

    public function __construct(private readonly ReservationService $reservations) {}

    public function index(Request $request): View
    {
        $hotel = $this->currentHotel($request);

        $status = in_array($request->query('status'), [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED], true)
            ? $request->query('status')
            : null;

        $bookings = $hotel->bookings()
            ->with('roomType')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.bookings.index', compact('hotel', 'bookings', 'status'));
    }

    public function show(Request $request, Booking $booking): View
    {
        $hotel = $this->currentHotel($request);
        abort_if($booking->hotel_id !== $hotel->id, 404);

        $booking->load(['roomType', 'member', 'internalNotes.author']);
        $history = AuditLog::where('hotel_id', $hotel->id)
            ->where('subject_type', $booking->getMorphClass())
            ->where('subject_id', $booking->id)
            ->with('user')
            ->latest('id')
            ->get();

        return view('admin.bookings.show', compact('hotel', 'booking', 'history'));
    }

    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        $hotel = $this->currentHotel($request);
        abort_if($booking->hotel_id !== $hotel->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', [Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED])],
        ]);

        $before = $booking->status;

        $changed = DB::transaction(function () use ($booking, $data): bool {
            $current = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            // A cancelled booking has given its rooms back; it cannot be revived without re-checking availability.
            if ($current->status === Booking::STATUS_CANCELLED || $current->status === $data['status']) {
                return false;
            }

            if ($data['status'] === Booking::STATUS_CANCELLED) {
                $this->reservations->releaseInventory($current);
            }

            $current->update(['status' => $data['status']]);

            return true;
        });

        if (! $changed) {
            return back()->withErrors(['status' => "Booking {$booking->reference} is already {$booking->fresh()->status} and cannot be changed to {$data['status']}."]);
        }

        AuditLog::record($hotel->id, 'booking.status', "Changed status from {$before} to {$data['status']}", $booking, $request->user(), ['from' => $before, 'to' => $data['status']]);

        return back()->with('status', "Booking {$booking->reference} marked as {$data['status']}.");
    }
}
