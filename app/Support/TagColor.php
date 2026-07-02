<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TagColor
{
    public const HEX_PATTERN = '/^#[0-9A-Fa-f]{6}$/';

    public static function hasAnyConfiguredColors(): bool
    {
        return self::tableHasConfiguredColors('genres')
            || self::tableHasConfiguredColors('genre_groups');
    }

    public static function normalize(mixed $color): ?string
    {
        $color = trim((string) $color);

        if ($color === '') {
            return null;
        }

        return preg_match(self::HEX_PATTERN, $color) === 1
            ? strtolower($color)
            : null;
    }

    public static function isValid(mixed $color): bool
    {
        return self::normalize($color) !== null;
    }

    /**
     * @param  iterable<int|string>  $genreIds
     * @return Collection<int, array{color: ?string, text_color: ?string, style: string, has_background_color: bool, has_font_color: bool}>
     */
    public static function effectiveColorPairsForGenreIds(iterable $genreIds): Collection
    {
        $ids = collect($genreIds)
            ->map(fn($id): string => (string) $id)
            ->filter(fn(string $id): bool => ctype_digit($id))
            ->map(fn(string $id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $tagColors = DB::table('genres')
            ->whereIn('id', $ids)
            ->get(['id', 'color', 'text_color'])
            ->keyBy('id');

        $groupColors = self::firstOrderedGroupColorPairs($ids);

        return $ids->mapWithKeys(function (int $id) use ($tagColors, $groupColors): array {
            $tag = $tagColors->get($id);
            $groupColor = $groupColors->get($id, ['color' => null, 'text_color' => null]);
            $color = self::normalize($groupColor['color'])
                ?? self::normalize($tag?->color ?? null);
            $textColor = self::normalize($groupColor['text_color'])
                ?? self::normalize($tag?->text_color ?? null);

            return [$id => self::pair($color, $textColor)];
        });
    }

    /**
     * @param  iterable<string>  $titleKeys
     * @return Collection<string, array{color: ?string, text_color: ?string, style: string, has_background_color: bool, has_font_color: bool}>
     */
    public static function effectiveColorPairsForTitleKeys(iterable $titleKeys): Collection
    {
        $keys = collect($titleKeys)
            ->map(fn($key): string => trim((string) $key))
            ->filter()
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return collect();
        }

        $genres = DB::table('genres')
            ->whereIn('title_key', $keys)
            ->get(['id', 'title_key']);

        $colors = self::effectiveColorPairsForGenreIds($genres->pluck('id'));

        return $genres->mapWithKeys(fn($genre): array => [
            $genre->title_key => $colors->get((int) $genre->id),
        ]);
    }

    /**
     * @return array{color: ?string, text_color: ?string, style: string, has_background_color: bool, has_font_color: bool}
     */
    public static function pair(mixed $color, mixed $textColor): array
    {
        $color = self::normalize($color);
        $textColor = self::normalize($textColor);

        return [
            'color' => $color,
            'text_color' => $textColor,
            'style' => self::styleAttribute($color, $textColor),
            'has_background_color' => $color !== null,
            'has_font_color' => $textColor !== null,
        ];
    }

    /**
     * @return array{color: ?string, text_color: ?string, color_style: string, has_background_color: bool, has_font_color: bool}
     */
    public static function viewData(mixed $color, mixed $textColor): array
    {
        $colors = self::pair($color, $textColor);

        return [
            'color' => $colors['color'],
            'text_color' => $colors['text_color'],
            'color_style' => $colors['style'],
            'has_background_color' => $colors['has_background_color'],
            'has_font_color' => $colors['has_font_color'],
        ];
    }

    /**
     * @param  iterable<object>  $groups
     * @return array{color: ?string, text_color: ?string}
     */
    public static function firstGroupColorPair(iterable $groups): array
    {
        $colors = ['color' => null, 'text_color' => null];

        foreach ($groups as $group) {
            if ($colors['color'] === null) {
                $colors['color'] = self::normalize($group->color ?? null);
            }

            if ($colors['text_color'] === null) {
                $colors['text_color'] = self::normalize($group->text_color ?? null);
            }

            if ($colors['color'] !== null && $colors['text_color'] !== null) {
                break;
            }
        }

        return $colors;
    }

    public static function styleAttribute(?string $color, ?string $textColor = null): string
    {
        $color = self::normalize($color);
        $textColor = self::normalize($textColor);

        $style = [];

        if ($color !== null) {
            $style[] = "--tag-color: {$color};";
        }

        if ($textColor !== null) {
            $style[] = "--tag-text-color: {$textColor};";
        }

        return implode(' ', $style);
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, array{color: ?string, text_color: ?string}>
     */
    private static function firstOrderedGroupColorPairs(Collection $ids): Collection
    {
        $colors = [];

        DB::table('genre_group_genre')
            ->join('genre_groups', 'genre_groups.id', '=', 'genre_group_genre.genre_group_id')
            ->whereIn('genre_group_genre.genre_id', $ids)
            ->whereAny(['genre_groups.color', 'genre_groups.text_color'], '<>', '')
            ->orderBy('genre_groups.order')
            ->orderBy('genre_group_genre.order')
            ->orderBy('genre_groups.title')
            ->get([
                'genre_group_genre.genre_id',
                'genre_groups.color',
                'genre_groups.text_color',
            ])
            ->each(function ($group) use (&$colors): void {
                $genreId = (int) $group->genre_id;
                $colors[$genreId] ??= ['color' => null, 'text_color' => null];

                if ($colors[$genreId]['color'] === null) {
                    $colors[$genreId]['color'] = self::normalize($group->color ?? null);
                }

                if ($colors[$genreId]['text_color'] === null) {
                    $colors[$genreId]['text_color'] = self::normalize($group->text_color ?? null);
                }
            });

        return collect($colors);
    }

    private static function tableHasConfiguredColors(string $table): bool
    {
        return DB::table($table)
            ->whereAny(['color', 'text_color'], '<>', '')
            ->exists();
    }
}
