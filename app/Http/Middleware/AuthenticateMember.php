<?php

namespace App\Http\Middleware;

use App\Core\Member\Models\Member;
use App\Models\Hotel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a signed-in member through only on the hotel they belong to. A member
 * of another hotel — the guard is shared by the whole browser session — is
 * sent to this hotel's own sign-in page, never into someone else's account.
 */
class AuthenticateMember
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Hotel $hotel */
        $hotel = $request->attributes->get(EnsureMembersEnabled::HOTEL_ATTRIBUTE);
        $member = Auth::guard(Member::GUARD)->user();

        if (! $member || ! $member->belongsToTenant($hotel)) {
            return redirect()->route('member.login', ['hotelSlug' => $hotel->slug, 'lang' => $request->query('lang')]);
        }

        return $next($request);
    }
}
