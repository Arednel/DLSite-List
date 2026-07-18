<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ProductIndexSortField: string
{
    use ProvidesOptions;

    case RJ = 'rj';
    case Score = 'score';
    case Series = 'series';
    case AgeCategory = 'age_category';
    case Progress = 'progress';
    case Priority = 'priority';
    case TotalTimesReListened = 'num_re_listen_times';
    case ReListenValue = 're_listen_value';
    case StartDate = 'start_date';
    case FinishDate = 'end_date';
    case AddedToTheSiteDate = 'created_at';
    case UpdatedAt = 'updated_at';
    case Circle = 'circle';
    case Scenario = 'scenario';
    case Illustration = 'illustration';
    case VoiceActor = 'voice_actor';
    case Author = 'author';

    public function label(): string
    {
        return match ($this) {
            self::RJ => __('RJ / Title'),
            self::Score => __('Score'),
            self::Series => __('Series'),
            self::AgeCategory => __('Age'),
            self::Progress => __('Progress'),
            self::Priority => __('Priority'),
            self::TotalTimesReListened => __('Total Times Re-listened'),
            self::ReListenValue => __('Re-listen Value'),
            self::StartDate => __('Start Date'),
            self::FinishDate => __('Finish Date'),
            self::AddedToTheSiteDate => __('Added to the site Date'),
            self::UpdatedAt => __('Updated Date'),
            self::Circle => __('Circle'),
            self::Scenario => __('Scenario Author'),
            self::Illustration => __('Illustration Author'),
            self::VoiceActor => __('Voice Actor'),
            self::Author => __('Author'),
        };
    }

    public function sqlColumn(): string
    {
        return match ($this) {
            self::RJ => 'rj_number',
            self::StartDate => 'start_date_sort',
            self::FinishDate => 'end_date_sort',
            default => $this->value,
        };
    }

    public function isHiddenByDefault(): bool
    {
        return in_array($this, [
            self::UpdatedAt,
            self::Circle,
            self::Scenario,
            self::Illustration,
            self::VoiceActor,
            self::Author,
        ], true);
    }

    /**
     * @return list<array{field: string, label: string, visible: bool}>
     */
    public static function normalizeLayout(mixed $layout): array
    {
        $submittedRows = is_array($layout) ? $layout : [];
        $rowsByField = [];
        $submittedOrder = [];

        foreach ($submittedRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $field = self::tryFrom((string) ($row['field'] ?? ''));

            if (! $field || isset($rowsByField[$field->value])) {
                continue;
            }

            $rowsByField[$field->value] = self::layoutRow(
                $field,
                filter_var($row['visible'] ?? false, FILTER_VALIDATE_BOOL),
            );
            $submittedOrder[] = $field->value;
        }

        $orderedRows = [];

        foreach ($submittedOrder as $field) {
            $orderedRows[$field] = $rowsByField[$field];
        }

        foreach (self::cases() as $field) {
            if (! isset($orderedRows[$field->value])) {
                $orderedRows[$field->value] = self::layoutRow($field, ! $field->isHiddenByDefault());
            }
        }

        return array_values($orderedRows);
    }

    /**
     * @return list<array{field: string, visible: bool}>
     */
    public static function storageLayout(mixed $layout): array
    {
        return collect(self::normalizeLayout($layout))
            ->map(fn(array $row): array => [
                'field' => $row['field'],
                'visible' => $row['visible'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{field: string, label: string, visible: bool}>  $layout
     * @return array<string, string>
     */
    public static function optionsFromLayout(array $layout): array
    {
        return collect(self::normalizeLayout($layout))
            ->filter(fn(array $row): bool => $row['visible'])
            ->mapWithKeys(function (array $row): array {
                $field = self::tryFrom($row['field']);

                return $field ? [$field->value => $field->label()] : [];
            })
            ->all();
    }

    private static function layoutRow(self $field, bool $visible): array
    {
        return [
            'field' => $field->value,
            'label' => $field->label(),
            'visible' => $visible,
        ];
    }
}
