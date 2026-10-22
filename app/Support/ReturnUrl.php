<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Resolves the "Go back" target of create forms.
 *
 * Only root-relative paths and absolute http(s) URLs on the application's own
 * origin are accepted, so a crafted return_url (e.g. javascript: or an external
 * site) can never end up in a rendered link.
 */
final class ReturnUrl
{
    public static function fromRequest(Request $request, ?string $fallback = null): string
    {
        $fallback ??= route('index');
        $candidate = $request->has('return_url')
            ? $request->input('return_url')
            : $request->old('return_url');
        $candidate = is_scalar($candidate) ? trim((string) $candidate) : '';

        if (self::isSafe($candidate, $request)) {
            return $candidate;
        }

        $previous = URL::previous($fallback);

        return self::isSafe($previous, $request) ? $previous : $fallback;
    }

    public static function isSafe(string $url, Request $request): bool
    {
        if ($url === '' || preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || blank($parts['host'] ?? null)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return false;
        }

        return in_array(self::origin($parts), self::allowedOrigins($request), true);
    }

    private static function origin(array $parts): string
    {
        $scheme = strtolower($parts['scheme']);
        $port = $parts['port'] ?? null;

        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        return $scheme . '://' . strtolower($parts['host']) . ($port === null ? '' : ':' . $port);
    }

    /**
     * @return list<string>
     */
    private static function allowedOrigins(Request $request): array
    {
        $origins = [];

        foreach ([$request->getSchemeAndHttpHost(), (string) config('app.url')] as $base) {
            $parts = parse_url($base);

            if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
                $origins[] = self::origin($parts);
            }
        }

        return array_values(array_unique($origins));
    }
}
