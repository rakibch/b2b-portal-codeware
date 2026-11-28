<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locale resolution: Accept-Language header (sent by the SPA on every request)
 * → authenticated user's saved preference → app default.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('b2b.supported_locales', ['en', 'bn']);

        $locale = $this->fromHeader($request, $supported)
            ?? $this->fromUser($request, $supported)
            ?? config('app.locale');

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);
        $response->setVary(array_unique(array_merge($response->getVary(), ['Accept-Language'])));

        return $response;
    }

    private function fromHeader(Request $request, array $supported): ?string
    {
        if (! $request->headers->has('Accept-Language')) {
            return null;
        }

        $preferred = $request->getPreferredLanguage($supported);

        // getPreferredLanguage falls back to the first supported locale; only accept a real match.
        foreach ($request->getLanguages() as $language) {
            if (strtolower(substr($language, 0, 2)) === $preferred) {
                return $preferred;
            }
        }

        return null;
    }

    private function fromUser(Request $request, array $supported): ?string
    {
        $locale = $request->user()?->locale;

        return in_array($locale, $supported, true) ? $locale : null;
    }
}
