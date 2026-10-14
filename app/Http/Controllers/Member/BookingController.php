<?php

namespace App\Http\Controllers\Member;

use App\Core\Member\Models\Member;
use App\Core\Member\Support\MemberLabels;
use App\Http\Controllers\Concerns\ResolvesMemberPages;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hotel;
use App\Services\Reservation\BookingChangeService;
use App\Services\Reservation\MemberBookings;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Moving or cancelling a stay from the member's own page — no reference and
 * contact to type, because the booking is already theirs.
 */
class BookingController extends Controller
{
    use ResolvesMemberPages;

    public function __construct(
        private readonly MemberBookings $memberBookings,
        private readonly BookingChangeService $changes,
    ) {}

    public function reschedule(Request $request, string $hotelSlug, string $reference): RedirectResponse
    {
        $hotel = $this->memberHotel($request);
        $member = $request->user(Member::GUARD);
        $locale = $this->memberLocale($request, $hotel, $member);
        $labels = MemberLabels::for($locale);
        $booking = $this->ownBooking($member, $hotel, $reference);

        $data = $request->validate([
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now($hotel->timezone)->toDateString()],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
        ], ['required' => $labels['invalid'], 'date_format' => $labels['invalid'], 'after_or_equal' => $labels['check_in_past'], 'after' => $labels['check_out_after']]);

        $checkIn = CarbonImmutable::parse($data['check_in'])->startOfDay();
        $checkOut = CarbonImmutable::parse($data['check_out'])->startOfDay();

        if ($checkIn->diffInDays($checkOut) > ReservationService::MAX_NIGHTS) {
            return $this->back($hotel, $locale)->with('error', MemberLabels::fill($labels['too_long'], ['max' => ReservationService::MAX_NIGHTS]));
        }

        if (! $this->changes->isChangeable($booking, $hotel)) {
            return $this->back($hotel, $locale)->with('error', $labels['not_changeable']);
        }

        $moved = $this->changes->reschedule($booking, $hotel, $checkIn, $checkOut);

        return $moved
            ? $this->back($hotel, $locale)->with('status', $labels['rescheduled'])
            : $this->back($hotel, $locale)->with('error', $labels['unavailable']);
    }

    public function cancel(Request $request, string $hotelSlug, string $reference): RedirectResponse
    {
        $hotel = $this->memberHotel($request);
        $member = $request->user(Member::GUARD);
        $locale = $this->memberLocale($request, $hotel, $member);
        $labels = MemberLabels::for($locale);
        $booking = $this->ownBooking($member, $hotel, $reference);

        return $this->changes->cancel($booking, $hotel)
            ? $this->back($hotel, $locale)->with('status', $labels['cancelled_ok'])
            : $this->back($hotel, $locale)->with('error', $labels['not_changeable']);
    }

    /**
     * Only a booking made under this member: someone else's reference is a 404.
     */
    private function ownBooking(Member $member, Hotel $hotel, string $reference): Booking
    {
        return $this->memberBookings->forMember($member, $hotel)
            ->where('reference', strtoupper(trim($reference)))
            ->firstOrFail();
    }

    private function back(Hotel $hotel, string $locale): RedirectResponse
    {
        return redirect()->route('member.account', ['hotelSlug' => $hotel->slug, 'lang' => $locale]);
    }
}
