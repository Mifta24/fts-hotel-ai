<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentHotel;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The hotel's members: who signed up, what they booked, and what the team knows about them.
 */
class MemberController extends Controller
{
    use ResolvesCurrentHotel;

    public function index(Request $request): View
    {
        $hotel = $this->currentHotel($request);
        abort_unless($hotel->supportsMembers(), 404);

        $search = trim((string) $request->query('q'));

        $members = $hotel->members()
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $bookingCounts = Booking::where('hotel_id', $hotel->id)
            ->whereIn('member_id', $members->pluck('id'))
            ->selectRaw('member_id, count(*) as total')
            ->groupBy('member_id')
            ->pluck('total', 'member_id');

        return view('admin.members.index', compact('hotel', 'members', 'bookingCounts', 'search'));
    }

    public function show(Request $request, int $member): View
    {
        $hotel = $this->currentHotel($request);
        abort_unless($hotel->supportsMembers(), 404);

        $member = $hotel->members()->findOrFail($member);
        $member->load('internalNotes.author');

        $bookings = Booking::where('hotel_id', $hotel->id)
            ->where('member_id', $member->id)
            ->with('roomType')
            ->orderByDesc('check_in')
            ->orderByDesc('id')
            ->get();

        return view('admin.members.show', compact('hotel', 'member', 'bookings'));
    }
}
