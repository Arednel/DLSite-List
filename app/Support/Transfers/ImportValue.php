<?php

namespace App\Support\Transfers;

use App\Enums\ProductAgeCategory;
use App\Enums\ProductPriority;
use App\Enums\ProductProgress;
use App\Enums\ProductReListenValue;
use App\Enums\ProductScore;
use App\Models\LibraryImportItem;
use App\Models\Product;
use Illuminate\Support\Arr;

/** Bounded, human-readable values for the shared Refetch presenter. */
final class ImportValue
{
    public static function label(string $key): string
    {
        return __(match ($key) {
            'work_name' => 'Japanese Title',
            'work_name_english' => 'English Title',
            'description' => 'Japanese Description',
            'description_english' => 'English Description',
            'maker_id', 'maker' => 'Maker ID',
            'age_category' => 'Age',
            'rj_code' => 'RJ code',
            'tag-library' => 'Tag Library',
            'jp' => 'Japanese',
            'en' => 'English',
            'scenario' => 'Scenario Author',
            'voice_actor' => 'Voice Actor',
            'illustration' => 'Illustration Author',
            'custom_tags' => 'Custom Tags',
            'end_date' => 'Finish Date',
            'num_re_listen_times' => 'Total Times Re-listened',
            're_listen_value' => 'Re-listen Value',
            'memberships' => 'Tags in Groups',
            'relationships' => 'Parent Tags',
            default => ucwords(str_replace('_', ' ', $key)),
        });
    }

    public static function preview(LibraryImportItem $item, string $attribute): array
    {
        return self::previewValue(
            value: $item->getAttribute($attribute),
            category: $item->category,
            field: $item->metadata['field'] ?? $item->entity_key,
            runId: $item->library_transfer_run_id,
            baseline: $attribute === 'baseline',
        );
    }

    public static function previewValue(mixed $value, string $category, string $field, int $runId, bool $baseline = false): array
    {
        if (in_array($category, ['cover', 'sample_images'], true)) {
            $paths = $baseline ? ($value['references'] ?? []) : array_map(
                fn($file) => route('options.transfers.image', [$runId, $file['entry_id']]),
                $value['files'] ?? [],
            );
            $paths = is_array($paths) ? $paths : array_filter([$paths]);
            if ($baseline) {
                $paths = array_map(Product::versionedImagePath(...), $paths);
            }

            return ['value' => array_slice($paths, 0, 8), 'image' => true, 'truncated' => count($paths) > 8];
        }
        $budget = 250;
        $truncated = false;
        $display = self::display($value, $field, $budget, $truncated);

        return ['value' => $display, 'image' => false, 'truncated' => $truncated];
    }

    /** @return list<string> */
    public static function newWorkJsonPaths(string $category): array
    {
        $paths = match (true) {
            in_array($category, ['titles', 'descriptions'], true) => [$category],
            isset(ImportReview::WORK_SCALAR_CATEGORIES[$category]) => [implode('->', ImportReview::WORK_SCALAR_CATEGORIES[$category])],
            in_array($category, ImportReview::WORK_CONTRIBUTOR_CATEGORIES, true) => ['contributors->' . $category],
            $category === 'tags' => ['tags->jp', 'tags->en'],
            $category === 'custom_tags' => ['tags->custom'],
            in_array($category, ['cover', 'sample_images'], true) => [$category],
            default => [],
        };

        return array_map(fn(string $path): string => 'incoming->' . $path, $paths);
    }

    /** @return list<array{key: string, label: string, preview: array{value: mixed, image: bool, truncated: bool}}> */
    public static function newWorkChanges(LibraryImportItem $item, string $category): array
    {
        if (isset($item->incoming_preview['new_work'][$category])) {
            return $item->incoming_preview['new_work'][$category];
        }

        $record = $item->incoming ?? [];
        if (! is_array($record)) {
            return [];
        }

        $values = [];
        if (in_array($category, ['titles', 'descriptions'], true)) {
            $values = is_array($record[$category] ?? null) ? $record[$category] : [];
        } elseif (isset(ImportReview::WORK_SCALAR_CATEGORIES[$category])) {
            [$group, $field] = ImportReview::WORK_SCALAR_CATEGORIES[$category];
            if (is_array($record[$group] ?? null) && array_key_exists($field, $record[$group])) {
                $values[$field] = $record[$group][$field];
            }
        } elseif (in_array($category, ImportReview::WORK_CONTRIBUTOR_CATEGORIES, true)) {
            if (is_array($record['contributors'] ?? null) && array_key_exists($category, $record['contributors'])) {
                $values[$category] = $record['contributors'][$category];
            }
        } elseif ($category === 'tags') {
            $tags = is_array($record['tags'] ?? null) ? Arr::only($record['tags'], ['jp', 'en']) : [];
            if ($tags !== []) {
                $values['tags'] = $tags;
            }
        } elseif ($category === 'custom_tags') {
            if (is_array($record['tags'] ?? null) && array_key_exists('custom', $record['tags'])) {
                $values['custom_tags'] = $record['tags']['custom'];
            }
        } elseif (in_array($category, ['cover', 'sample_images'], true) && array_key_exists($category, $record)) {
            $values[$category] = $record[$category];
        }

        $changes = [];
        foreach ($values as $field => $value) {
            $changes[] = [
                'key' => $field,
                'label' => self::label($field),
                'preview' => self::previewValue($value, $category, (string) $field, $item->library_transfer_run_id),
            ];
        }

        return $changes;
    }

    private static function display(mixed $value, string $field, int &$budget, bool &$truncated): mixed
    {
        if (--$budget < 0) {
            $truncated = true;

            return '…';
        }
        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }
        $enum = match ($field) {
            'age_category' => ProductAgeCategory::class,
            'progress' => ProductProgress::class,
            'priority' => ProductPriority::class,
            'score' => ProductScore::class,
            're_listen_value' => ProductReListenValue::class,
            default => null,
        };
        if ($enum && (is_string($value) || is_int($value))) {
            return $enum::tryFrom($value)?->label() ?? (string) $value;
        }
        if (! is_array($value)) {
            if (is_string($value) && mb_strlen($value) > 4096) {
                $truncated = true;

                return mb_substr($value, 0, 4096) . '…';
            }

            return $value;
        }
        if ($value !== [] && array_diff(array_keys($value), ['year', 'month', 'day']) === []) {
            return implode('-', array_map(fn($part) => isset($value[$part]) ? str_pad((string) $value[$part], $part === 'year' ? 4 : 2, '0', STR_PAD_LEFT) : '?', ['year', 'month', 'day']));
        }
        $result = [];
        $list = array_is_list($value);
        foreach ($value as $key => $entry) {
            if ($budget <= 0 || count($result) >= 50) {
                $truncated = true;
                break;
            }
            if ($list && ! is_array($entry)) {
                $result[] = ['value' => self::display($entry, $field, $budget, $truncated)];
            } else {
                $result[$list ? __('Item :number', ['number' => $key + 1]) : self::label((string) $key)] = self::display($entry, (string) $key, $budget, $truncated);
            }
        }

        return $result;
    }
}
