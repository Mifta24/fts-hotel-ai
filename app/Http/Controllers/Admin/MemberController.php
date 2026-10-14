<?php

namespace App\Http\Controllers\Admin;

use App\Core\Member\Models\MemberPoint;
use App\Core\Member\Services\PointsLedger;
use App\Http\Controllers\Concerns\ResolvesCurrentHotel;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The hotel's members: who signed up, what they booked, and what the team knows about them.
 */
class MemberController extends Controller
{
    use ResolvesCurrentHotel;

    public function __construct(private readonly PointsLedger $ledger) {}

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

        $program = $hotel->loyaltyProgram();
        $balances = $program
            ? MemberPoint::whereIn('member_id', $members->pluck('id'))->selectRaw('member_id, sum(points) as total')->groupBy('member_id')->pluck('total', 'member_id')
            : collect();

        return view('admin.members.index', compact('hotel', 'members', 'bookingCounts', 'search', 'program', 'balances'));
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

        $program = $hotel->loyaltyProgram();
        $points = $program ? $member->pointsBalance() : 0;
        $pointsHistory = $program ? $member->points()->limit(50)->get() : collect();

        return view('admin.members.show', compact('hotel', 'member', 'bookings', 'program', 'points', 'pointsHistory'));
    }

    public function adjustPoints(Request $request, int $member): RedirectResponse
    {
        $hotel = $this->currentHotel($request);
        abort_unless($hotel->supportsLoyalty(), 404);

        $member = $hotel->members()->findOrFail($member);

        $data = $request->validate([
            'points' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
            'note' => ['required', 'string', 'max:200'],
        ]);

        $this->ledger->adjust($member, (int) $data['points'], $data['note'], $request->user()->name);
        AuditLog::record($hotel->id, 'member.points', 'Adjusted '.$member->displayName()."'s points by {$data['points']}", $member, $request->user(), ['points' => (int) $data['points'], 'note' => $data['note']]);

        return back()->with('status', 'Points updated.');
    }
}
