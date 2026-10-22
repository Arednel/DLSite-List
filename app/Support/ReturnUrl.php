<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Resolves the "Go back" target of create forms.
 *
 * Only root-relative paths and absolute http(s) URLs are accepted, so a crafted
 * return_url (e.g. javascript: or data:) can never end up in a rendered link.
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

        if (self::isSafe($candidate)) {
            return $candidate;
        }

        $previous = URL::previous($fallback);

        return self::isSafe($previous) ? $previous : $fallback;
    }

    public static function isSafe(string $url): bool
    {
        // Reject whitespace/control characters and backslashes, which browsers may
        // strip or normalize into a different scheme or host.
        if ($url === '' || preg_match('/[\x00-\x20\x7F\x5C]/', $url) === 1) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && filled($parts['host'] ?? null)
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }
}
