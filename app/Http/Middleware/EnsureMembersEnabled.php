<?php

namespace App\Http\Middleware;

use App\Models\Hotel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The member pages exist only for a published hotel that was given the member
 * add-on; for every other hotel they are simply not there.
 */
class EnsureMembersEnabled
{
    public const HOTEL_ATTRIBUTE = 'member_hotel';

    public function handle(Request $request, Closure $next): Response
    {
        $hotel = Hotel::where('slug', $request->route('hotelSlug'))->first();

        abort_if(! $hotel || ! $hotel->isPublished() || ! $hotel->supportsMembers(), 404);

        $request->attributes->set(self::HOTEL_ATTRIBUTE, $hotel);

        return $next($request);
    }
}
