<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->stripHotelSlugOnCustomDomains();
    }

    /**
     * On a hotel's own domain the hotel is the whole site, so links to it
     * leave out the slug: https://hotel.example/rooms rather than /demo/rooms.
     */
    private function stripHotelSlugOnCustomDomains(): void
    {
        URL::formatPathUsing(function (string $path): string {
            $slug = request()->attributes->get('custom_domain_slug');

            if ($slug && ($path === "/{$slug}" || str_starts_with($path, "/{$slug}/"))) {
                return substr($path, strlen($slug) + 1) ?: '/';
            }

            return $path;
        });
    }

    /**
     * Every guest message costs an LLM round trip, so it is limited per
     * conversation and, as a backstop against rotating tokens, per address.
     * The address limits are generous because every guest on a hotel's Wi-Fi
     * shares one address.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('concierge-start', fn (Request $request) => Limit::perMinute(30)->by('start:'.$request->ip()));

        RateLimiter::for('concierge-message', fn (Request $request) => [
            Limit::perMinute(12)->by('conversation:'.($request->input('guest_token') ?: $request->ip())),
            Limit::perMinute(120)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('booking-manage', fn (Request $request) => [
            Limit::perMinute(10)->by('booking-ref:'.strtoupper((string) $request->input('reference')).'|'.$request->ip()),
            Limit::perMinute(60)->by('booking-ip:'.$request->ip()),
        ]);

        RateLimiter::for('member-code', fn (Request $request) => [
            Limit::perMinute(3)->by('member-code:'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by('member-code-ip:'.$request->ip()),
        ]);

        RateLimiter::for('member-verify', fn (Request $request) => [
            Limit::perMinute(10)->by('member-verify:'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(60)->by('member-verify-ip:'.$request->ip()),
        ]);

        RateLimiter::for('narration-speech', fn (Request $request) => Limit::perMinute(20)->by('speech:'.$request->ip()));

        RateLimiter::for('concierge-feedback', fn (Request $request) => Limit::perMinute(30)->by('feedback:'.($request->input('guest_token') ?: $request->ip())));

        RateLimiter::for('concierge-history', fn (Request $request) => [
            Limit::perMinute(20)->by('history-conversation:'.($request->input('guest_token') ?: $request->ip())),
            Limit::perMinute(300)->by('history:'.$request->ip()),
        ]);
    }
}
