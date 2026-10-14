<?php

use App\Http\Middleware\AuthenticateMember;
use App\Http\Middleware\EnsureHotelAbility;
use App\Http\Middleware\EnsureMembersEnabled;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\ResolveCustomDomain;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ResolveCustomDomain::class);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->alias(['hotel.can' => EnsureHotelAbility::class, 'platform.admin' => EnsurePlatformAdmin::class, 'member.enabled' => EnsureMembersEnabled::class, 'member.auth' => AuthenticateMember::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
