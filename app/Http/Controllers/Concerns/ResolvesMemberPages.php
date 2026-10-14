<?php

namespace App\Http\Controllers\Concerns;

use App\Core\Member\Models\Member;
use App\Core\Member\Support\MemberLabels;
use App\Http\Middleware\EnsureMembersEnabled;
use App\Models\Hotel;
use Illuminate\Http\Request;

trait ResolvesMemberPages
{
    /**
     * The hotel the `member.enabled` middleware already vetted for this request.
     */
    protected function memberHotel(Request $request): Hotel
    {
        return $request->attributes->get(EnsureMembersEnabled::HOTEL_ATTRIBUTE);
    }

    /**
     * An explicit ?lang= wins, then the member's own choice, then the hotel's language.
     */
    protected function memberLocale(Request $request, Hotel $hotel, ?Member $member = null): string
    {
        $requested = $request->input('lang', $request->query('lang'));

        if (MemberLabels::supports($requested)) {
            return $requested;
        }

        return MemberLabels::supports($member?->locale) ? $member->locale : $hotel->memberDefaultLocale();
    }
}
