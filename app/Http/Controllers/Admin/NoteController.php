<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentHotel;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\HandoverRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    use ResolvesCurrentHotel;

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $hotel = $this->currentHotel($request);

        $subject = match ($type) {
            'booking' => $this->booking($request, $hotel->id, $id),
            'handover' => $this->handover($request, $hotel->id, $id),
            default => abort(404),
        };

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $subject->internalNotes()->create(['hotel_id' => $hotel->id, 'user_id' => $request->user()->id, 'body' => $data['body']]);
        AuditLog::record($hotel->id, 'note.added', 'Added an internal note', $subject, $request->user());

        return back()->with('status', 'Note saved. Guests cannot see it.');
    }

    private function booking(Request $request, int $hotelId, int $id): Booking
    {
        abort_unless($request->user()->canInHotel('bookings.view'), 403);

        return Booking::where('hotel_id', $hotelId)->findOrFail($id);
    }

    private function handover(Request $request, int $hotelId, int $id): HandoverRequest
    {
        abort_unless($request->user()->canInHotel('handovers'), 403);

        return HandoverRequest::whereHas('conversation', fn ($q) => $q->where('hotel_id', $hotelId))->findOrFail($id);
    }
}
