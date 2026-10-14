<?php

namespace App\Services\Reservation;

use App\Core\Member\Models\Member;
use App\Models\Booking;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * The hotel's side of the member core: which bookings belong to a member.
 */
class MemberBookings
{
    /**
     * The member signed in on this hotel in the current session, if the hotel has the add-on.
     */
    public function signedIn(Hotel $hotel): ?Member
    {
        $member = Auth::guard(Member::GUARD)->user();

        return $hotel->supportsMembers() && $member?->belongsToTenant($hotel) ? $member : null;
    }

    /**
     * The percent off a member gets today: the discount of the tier their points
     * have reached. Zero for guests, for another hotel's members and when the
     * hotel has no loyalty add-on. Fixed on a booking when it is made.
     */
    public function discountFor(Hotel $hotel, ?Member $member): float
    {
        $program = $hotel->loyaltyProgram();

        return $member && $program && $member->belongsToTenant($hotel) ? $program->discountFor($member->pointsBalance()) : 0.0;
    }

    /**
     * A member's reservations at the hotel, the next stay first.
     *
     * @return Builder<Booking>
     */
    public function forMember(Member $member, Hotel $hotel): Builder
    {
        return Booking::where('hotel_id', $hotel->id)
            ->where('member_id', $member->id)
            ->with('roomType')
            ->orderByDesc('check_in')
            ->orderByDesc('id');
    }

    /**
     * Claims the hotel's earlier, unclaimed bookings made with the member's
     * email. The email was proven at sign-in, so the match is safe; phone
     * numbers are not, and are never used to link.
     */
    public function adopt(Member $member, Hotel $hotel): int
    {
        return Booking::where('hotel_id', $hotel->id)
            ->whereNull('member_id')
            ->whereRaw('LOWER(guest_email) = ?', [$member->email])
            ->update(['member_id' => $member->id]);
    }
}
