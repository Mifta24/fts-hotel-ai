<?php

namespace App\Http\Middleware;

use App\Models\Hotel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * A hotel with its own domain is served from the root of that domain: a visit
 * to https://hotel.example/rooms is handled as /{slug}/rooms. The admin, the
 * platform screens and the other shared endpoints keep their own addresses.
 */
class ResolveCustomDomain
{
    /**
     * First path segments that belong to the application, not to the hotel.
     */
    public const SHARED_PREFIXES = ['admin', 'platform', 'narration', 'up', 'storage', 'build', 'images', 'fonts'];

    public static function cacheKey(string $host): string
    {
        return 'custom-domain:'.strtolower($host);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if ($host === strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST))) {
            return $next($request);
        }

        $slug = $this->slugFor($host);

        if ($slug === '') {
            return $next($request);
        }

        $path = $request->getPathInfo();
        $first = explode('/', ltrim($path, '/'))[0];

        $request->attributes->set('custom_domain_slug', $slug);

        if (in_array($first, self::SHARED_PREFIXES, true)) {
            return $next($request);
        }

        $query = (string) $request->server->get('QUERY_STRING', '');
        $request->server->set('REQUEST_URI', '/'.$slug.($path === '/' ? '' : $path).($query !== '' ? '?'.$query : ''));
        $request->initialize($request->query->all(), $request->request->all(), $request->attributes->all(), $request->cookies->all(), $request->files->all(), $request->server->all(), $request->getContent());

        return $next($request);
    }

    private function slugFor(string $host): string
    {
        try {
            return Cache::remember(self::cacheKey($host), 300, fn () => (string) Hotel::where('custom_domain', $host)->value('slug'));
        } catch (\Throwable) {
            return '';
        }
    }
}
