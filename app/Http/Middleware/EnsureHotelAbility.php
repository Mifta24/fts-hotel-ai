<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps an admin screen to the roles allowed to use it (see HotelUser::ABILITIES).
 */
class EnsureHotelAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        abort_unless($request->user()?->canInHotel($ability), 403, 'Your role cannot open this page.');

        return $next($request);
    }
}
