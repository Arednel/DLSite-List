<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/** Resolves the same-site exit target preserved across the create-mode flow. */
final class ReturnUrl
{
    public static function fromRequest(Request $request): string
    {
        $root = $request->root();
        $fallback = route('index');
        $returnUrl = $request->input('return_url') ?? $request->old('return_url');

        // DLSite Create, Custom Create, and Bulk Import are one flow. 
        // Keep the original page as the exit target when switching modes instead of treating another create page as the new "Go back" destination.
        if (is_string($returnUrl) && self::isSameSite($root, $returnUrl)) {
            return $returnUrl;
        }

        $previous = URL::previous($fallback);

        return self::isSameSite($root, $previous)
            ? $previous
            : $fallback;
    }

    private static function isSameSite(string $root, string $url): bool
    {
        return $url === $root || str_starts_with($url, "{$root}/");
    }
}
