<?php

namespace App\Support;

final class TagInput
{
    /**
     * Parse a comma-separated tag list, keeping quoted commas intact.
     */
    public static function parse(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $parts = array_map('trim', str_getcsv($value, ',', '"', '\\'));

        return array_values(array_filter($parts, fn (string $part) => $part !== ''));
    }

    /**
     * Format tags back into CSV form so commas stay readable in textareas.
     */
    public static function format(iterable $tags): string
    {
        $formatted = [];

        foreach ($tags as $tag) {
            $tag = (string) $tag;

            $needsQuotes = str_contains($tag, ',')
                || str_contains($tag, '"')
                || str_contains($tag, "\n")
                || str_contains($tag, "\r");

            $formatted[] = $needsQuotes
                ? '"' . str_replace('"', '""', $tag) . '"'
                : $tag;
        }

        return implode(', ', $formatted);
    }
}
