<?php

namespace App\Enums;

use App\Enums\Concerns\ProvidesOptions;

enum ProductField: string
{
    use ProvidesOptions;

    case RjCode = 'rj_code';
    case Title = 'title';
    case Image = 'image';
    case SampleImages = 'sample_images';
    case Score = 'score';
    case Series = 'series';
    case AgeCategory = 'age_category';
    case Progress = 'progress';
    case Circle = 'circle';
    case Scenario = 'scenario';
    case Illustration = 'illustration';
    case VoiceActor = 'voice_actor';
    case Author = 'author';
    case Description = 'description';
    case Tags = 'tags';
    case Notes = 'notes';
    case StartDate = 'start_date';
    case FinishDate = 'end_date';
    case TotalTimesReListened = 'num_re_listen_times';
    case ReListenValue = 're_listen_value';
    case Priority = 'priority';

    public function label(): string
    {
        return match ($this) {
            self::RjCode => 'RJ Code',
            self::Title => 'Title',
            self::Image => 'Image',
            self::SampleImages => 'Sample Images',
            self::Score => 'Score',
            self::Series => 'Series',
            self::AgeCategory => 'Age',
            self::Progress => 'Progress',
            self::Circle => 'Circle',
            self::Scenario => 'Scenario Author',
            self::Illustration => 'Illustration Author',
            self::VoiceActor => 'Voice Actor',
            self::Author => 'Author',
            self::Description => 'Description',
            self::Tags => 'Tags',
            self::Notes => 'Notes',
            self::StartDate => 'Start Date',
            self::FinishDate => 'Finish Date',
            self::TotalTimesReListened => 'Total Times Re-listened',
            self::ReListenValue => 'Re-listen Value',
            self::Priority => 'Priority',
        };
    }

    public function contributorRole(): ?ProductContributorRole
    {
        return match ($this) {
            self::Circle => ProductContributorRole::Circle,
            self::Scenario => ProductContributorRole::Scenario,
            self::Illustration => ProductContributorRole::Illustration,
            self::VoiceActor => ProductContributorRole::VoiceActor,
            self::Author => ProductContributorRole::Author,
            default => null,
        };
    }

    public function sortField(): ?ProductIndexSortField
    {
        return match ($this) {
            self::Title => ProductIndexSortField::RJ,
            self::Score => ProductIndexSortField::Score,
            self::Series => ProductIndexSortField::Series,
            self::AgeCategory => ProductIndexSortField::AgeCategory,
            self::Progress => ProductIndexSortField::Progress,
            self::Priority => ProductIndexSortField::Priority,
            self::TotalTimesReListened => ProductIndexSortField::TotalTimesReListened,
            self::ReListenValue => ProductIndexSortField::ReListenValue,
            self::StartDate => ProductIndexSortField::StartDate,
            self::FinishDate => ProductIndexSortField::FinishDate,
            default => null,
        };
    }

    public function isContributor(): bool
    {
        return $this->contributorRole() !== null;
    }

    public function isHiddenByDefault(string $surface = ''): bool
    {
        if (in_array($surface, ['edit', 'quick_add'], true) && $this === self::AgeCategory) {
            return true;
        }

        return in_array($this, [
            self::Circle,
            self::Scenario,
            self::Illustration,
            self::VoiceActor,
            self::Author,
            self::Description,
        ], true);
    }

    public function isEditableByDefault(string $surface = ''): bool
    {
        return in_array($this, [
            self::Score,
            self::Series,
            self::AgeCategory,
            self::Progress,
            self::Tags,
            self::Notes,
            self::StartDate,
            self::FinishDate,
            self::TotalTimesReListened,
            self::ReListenValue,
            self::Priority,
        ], true);
    }

    public function isVisibilityLocked(string $surface): bool
    {
        return match ($surface) {
            'index', 'edit' => $this === self::Title,
            'quick_add' => $this === self::RjCode,
            'custom_quick_add' => in_array($this, [
                self::RjCode,
                self::Title,
                self::AgeCategory,
                self::Image,
            ], true),
            default => false,
        };
    }
}
