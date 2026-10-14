<?php

namespace App\Http\Controllers\Member;

use App\Core\Member\Models\Member;
use App\Core\Member\Support\MemberLabels;
use App\Http\Controllers\Concerns\ResolvesMemberPages;
use App\Http\Controllers\Controller;
use App\Services\Reservation\BookingChangeService;
use App\Services\Reservation\MemberBookings;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A member's own page: their details and the reservations made under them.
 */
class AccountController extends Controller
{
    use ResolvesMemberPages;

    public function __construct(
        private readonly MemberBookings $memberBookings,
        private readonly BookingChangeService $changes,
    ) {}

    public function show(Request $request): View
    {
        $hotel = $this->memberHotel($request);
        $member = $request->user(Member::GUARD);
        $locale = $this->memberLocale($request, $hotel, $member);

        $bookings = $this->memberBookings->forMember($member, $hotel)
            ->get()
            ->each(fn ($booking) => $booking->setAttribute('changeable', $this->changes->isChangeable($booking, $hotel)));

        return view('member.account', [
            'hotel' => $hotel,
            'member' => $member,
            'locale' => $locale,
            'labels' => MemberLabels::for($locale),
            'bookings' => $bookings,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $hotel = $this->memberHotel($request);
        $member = $request->user(Member::GUARD);
        $locale = $this->memberLocale($request, $hotel, $member);
        $labels = MemberLabels::for($locale);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:'.ReservationService::PHONE_PATTERN],
            'locale' => ['required', Rule::in(MemberLabels::SUPPORTED_LOCALES)],
        ], ['required' => $labels['invalid'], 'regex' => $labels['invalid'], 'max' => $labels['invalid'], 'in' => $labels['invalid']]);

        $member->update($data);

        return redirect()
            ->route('member.account', ['hotelSlug' => $hotel->slug, 'lang' => $data['locale']])
            ->with('status', MemberLabels::for($data['locale'])['saved']);
    }
}
