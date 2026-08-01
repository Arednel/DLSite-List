<?php

namespace App\Enums;

enum RefetchCategory: string
{
    case Titles = 'titles';
    case Descriptions = 'descriptions';
    case Series = 'series';
    case Age = 'age';
    case Circle = 'circle';
    case Maker = 'maker';
    case Scenario = 'scenario';
    case VoiceActor = 'voice_actor';
    case Illustration = 'illustration';
    case Author = 'author';
    case Tags = 'tags';
    case Cover = 'cover';
    case SampleImages = 'sample_images';

    public function label(): string
    {
        return match ($this) {
            self::Titles => __('Titles'),
            self::Descriptions => __('Descriptions'),
            self::Series => __('Series'),
            self::Age => __('Age'),
            self::Circle => __('Circle'),
            self::Maker => __('Maker ID'),
            self::Scenario => __('Scenario Author'),
            self::VoiceActor => __('Voice Actor'),
            self::Illustration => __('Illustration Author'),
            self::Author => __('Author'),
            self::Tags => __('Tags'),
            self::Cover => __('Cover'),
            self::SampleImages => __('Sample Images'),
        };
    }

    public function contributorRole(): ?ProductContributorRole
    {
        return match ($this) {
            self::Circle => ProductContributorRole::Circle,
            self::Scenario => ProductContributorRole::Scenario,
            self::VoiceActor => ProductContributorRole::VoiceActor,
            self::Illustration => ProductContributorRole::Illustration,
            self::Author => ProductContributorRole::Author,
            default => null,
        };
    }

    public function isImage(): bool
    {
        return $this === self::Cover || $this === self::SampleImages;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
